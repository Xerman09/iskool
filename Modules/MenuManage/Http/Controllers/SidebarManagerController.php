<?php

namespace Modules\MenuManage\Http\Controllers;

use App\GlobalVariable;
use Illuminate\Http\Request;
use App\Traits\SidebarDataStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Cache;
use Modules\MenuManage\Entities\Sidebar;
use Modules\RolePermission\Entities\Permission;
use Modules\MenuManage\Entities\PermissionSection;
use Modules\RolePermission\Entities\AssignPermission;
use Modules\MenuManage\Http\Requests\SectionRequestFrom;

class SidebarManagerController extends Controller
{
    use SidebarDataStore;

    public function __construct()
    {
        /* Every action here reads or writes a layout, and the picker decides whose.
           Set once so no action can forget and quietly edit the editor's own role. */
        $this->middleware(function ($request, $next) {
            Sidebar::$layoutRoleId = sidebarManagerRoleId();

            return $next($request);
        });
    }

    /**
     * /sidebar-manager had no index method, so reaching it was a 500. The manager lives at
     * menumanage.index; send anyone with the old link there.
     */
    public function index()
    {
        return redirect()->route('menumanage.index');
    }

    /** Switches which role the manager is arranging. */
    public function selectRole(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        session(['sidebar_manager_role_id' => (int) $request->role_id]);

        return redirect()->route('menumanage.index');
    }
    /**
     * Renames any sidebar entry - a section header or a menu item, stock or custom.
     *
     * The label goes to permissions.custom_name, which menuLabel() prefers over the
     * shipped name/lang_name, so an empty value restores the original. Unlike the rest of
     * this controller the label is school-wide rather than per user: a menu entry should
     * not be called one thing for the registrar and another for the dean.
     */
    public function menuRename(Request $request)
    {
        if (config('app.app_sync')) {
            Toastr::error('Restricted in demo mode');
            return back();
        }

        $request->validate([
            'permission_id' => ['required', 'exists:permissions,id'],
            'custom_name' => ['nullable', 'string', 'max:191'],
        ]);

        $permission = Permission::findOrFail($request->permission_id);
        $permission->custom_name = filled($request->custom_name) ? trim($request->custom_name) : null;
        $permission->save();

        $this->forgetSidebarCaches();

        Toastr::success(filled($permission->custom_name)
            ? 'Menu renamed to "' . $permission->custom_name . '"'
            : 'Menu name reset to the default', 'Success');
        return redirect()->route('menumanage.index');
    }

    /**
     * sidebar_menus() caches the tree per user and keeps it forever, so a school-wide
     * label change has to drop every user's key, not just the editor's.
     */
    private function forgetSidebarCaches()
    {
        forgetAllSidebarCaches();
    }

    /**
     * Puts a menu into a section of the layout being arranged.
     *
     * Every menu a role is already eligible for is in its layout from the start, so the only
     * useful thing to add is one the role could not see before - which means the role flag on
     * the permission has to be set as well, since that is what decides whether a menu renders
     * for a role at all.
     *
     * This makes the menu visible. It does not grant the route: opening it still needs the
     * permission assigned under Role & Permission, exactly as for every other menu.
     */
    public function menuStore(Request $request)
    {
        if (config('app.app_sync')) {
            Toastr::error('Restricted in demo mode');
            return back();
        }

        $request->validate([
            'section_id' => ['required', 'exists:permissions,id'],
            'permission_id' => ['required', 'exists:permissions,id', 'different:section_id'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $permission = Permission::findOrFail($request->permission_id);

        /* Several roles at once, or the one being arranged if none is named. Placing
           a menu is the same act whoever it is for, and doing it one role at a time
           meant opening the manager four times to put one entry in four sidebars. */
        $roleIds = $request->filled('role_ids')
            ? array_values(array_unique(array_map('intval', $request->role_ids)))
            : [(int) sidebarRoleId()];

        foreach ($roleIds as $roleId) {
            /* The role flag on the permission is what decides whether a menu renders
               for a role at all, so it is set per role - otherwise the row is written
               and the menu still does not appear. */
            $flag = [2 => 'is_student', 3 => 'is_parent', 4 => 'is_teacher'][$roleId] ?? 'is_admin';
            if (!$permission->{$flag}) {
                $permission->{$flag} = 1;
                $permission->save();
            }

            $lastPosition = (int) Sidebar::whereNull('user_id')
                ->where('role_id', $roleId)
                ->where('parent', $request->section_id)
                ->max('position');

            Sidebar::updateOrCreate([
                'permission_id' => $permission->id,
                'user_id' => null,
                'role_id' => $roleId,
            ], [
                'parent' => (int) $request->section_id,
                'position' => $lastPosition + 1,
                'level' => $permission->type,
                'active_status' => 1,
                'ignore' => 0,
            ]);
        }

        $this->forgetSidebarCaches();

        /* The module's sidebar.js posts this over ajax and expects the three rendered
           panels back, the same shape the drag-and-drop handler gets. It sends
           X-Requested-With rather than an Accept header, so ajax() is the check. */
        if ($request->ajax() || $request->wantsJson()) {
            return $this->reloadWithData();
        }

        Toastr::success(menuLabel($permission) . ' added to the menu', 'Success');
        return redirect()->route('menumanage.index');
    }

    /**
     * Creates a new dropdown inside a section - the kind of entry "Admin Section" and
     * "Academics" are: a container with a label and no page of its own, which existing menu
     * items are then dragged into.
     *
     * A container is type 1 with permission_section 0; sections are permission_section 1 and
     * leaves are type 2. It needs a route because the column is the module's identifier for a
     * menu, but nothing has to answer at it: the sidebar renders a container with children as
     * a plain toggle, and validRouteUrl() yields an empty href for an unknown route, so an
     * empty new menu is inert rather than broken.
     */
    public function menuCreate(Request $request)
    {
        if (config('app.app_sync')) {
            Toastr::error('Restricted in demo mode');
            return back();
        }

        $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'icon' => ['nullable', 'string', 'max:100'],
            'section_id' => ['required', 'exists:permissions,id'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $roleIds = $request->filled('role_ids')
            ? array_map('intval', $request->role_ids)
            : [sidebarRoleId()];
        $roleIds = array_values(array_unique($roleIds));

        /* The route is an identifier, so keep it a slug and keep it unique - sectionStore
           lowercases the name as-is and leaves spaces in, which is not something to copy. */
        $base = \Illuminate\Support\Str::slug($request->name) ?: 'menu';
        $route = $base;
        $suffix = 2;
        while (Permission::where('route', $route)->exists()) {
            $route = $base . '-' . $suffix++;
        }

        $section = Permission::findOrFail($request->section_id);

        $flags = [];
        foreach ($roleIds as $roleId) {
            $flags[[2 => 'is_student', 3 => 'is_parent', 4 => 'is_teacher'][$roleId] ?? 'is_admin'] = 1;
        }

        $permission = Permission::create(array_merge([
            'name' => $request->name,
            // Left null when nothing is picked; the sidebar falls back to a generic mark.
            'icon' => $request->icon ?: null,
            'route' => $route,
            'parent_route' => $section->route,
            'sidebar_menu' => $section->route,
            'type' => 1,
            'permission_section' => 0,
            'is_menu' => 1,
            'status' => 1,
            'menu_status' => 1,
            'user_id' => null,
            'school_id' => auth()->user()->school_id,
            'position' => (int) Permission::max('position') + 1,
            /* Made here, so it can be deleted here - see deleteMenu(). A menu that
               shipped with the system or came with a module has a screen behind it
               and must not be removable. */
            'is_custom' => 1,
        ], $flags));

        $placed = [];
        $skipped = [];
        foreach ($roleIds as $roleId) {
            /* The chosen section only works for a role whose layout actually has it. */
            $parentId = Sidebar::whereNull('user_id')
                ->where('role_id', $roleId)
                ->whereNull('parent')
                ->where('permission_id', $section->id)
                ->exists()
                    ? $section->id
                    : Sidebar::whereNull('user_id')
                        ->where('role_id', $roleId)
                        ->whereNull('parent')
                        ->orderBy('position')
                        ->value('permission_id');

            if (!$parentId) {
                $skipped[] = $roleId;
                continue;
            }

            $lastPosition = (int) Sidebar::whereNull('user_id')
                ->where('role_id', $roleId)
                ->where('parent', $parentId)
                ->max('position');

            Sidebar::updateOrCreate([
                'permission_id' => $permission->id,
                'user_id' => null,
                'role_id' => $roleId,
            ], [
                'parent' => $parentId,
                'position' => $lastPosition + 1,
                'level' => 1,
                'active_status' => 1,
                'ignore' => 0,
                'school_id' => auth()->user()->school_id,
            ]);

            $placed[] = $roleId;
        }

        $this->forgetSidebarCaches();

        if ($request->ajax() || $request->wantsJson()) {
            return $this->reloadWithData();
        }

        $roleNames = \Illuminate\Support\Facades\DB::table('roles')->whereIn('id', $placed)->pluck('name')->implode(', ');
        Toastr::success(menuLabel($permission) . ' created for ' . ($roleNames ?: 'nobody'), 'Success');

        if ($skipped) {
            $skippedNames = \Illuminate\Support\Facades\DB::table('roles')->whereIn('id', $skipped)->pluck('name')->implode(', ');
            Toastr::warning('Skipped ' . $skippedNames . ' - no menu layout to add it to yet. Open the manager for that role once, then try again.', 'Skipped');
        }

        return redirect()->route('menumanage.index');
    }

    /**
     * Menus that are not in the layout being arranged, for the "add a menu" picker.
     *
     * Two things made this list read as duplicates. eskooly keeps a separate permission row
     * per role for some screens - Chat exists once for admins and again for parents, same
     * route - so those are collapsed to the one variant that suits the role being arranged.
     * Separately, genuinely different screens share a name: "Apply Leave" is both the staff
     * form and the parent one. Those stay, with the least ugly qualifier that tells them
     * apart, and names that are already unique are left clean.
     *
     * Sections are excluded - a section is not something you drop into a section.
     */
    public static function addableMenus()
    {
        $roleId = sidebarRoleId();
        $flag = [2 => 'is_student', 3 => 'is_parent', 4 => 'is_teacher'][$roleId] ?? 'is_admin';

        $alreadyIn = Sidebar::whereNull('user_id')
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->toArray();

        $menus = Permission::where('is_menu', 1)
            ->whereNotNull('route')
            ->where('permission_section', '!=', 1)
            ->whereNotIn('id', $alreadyIn)
            ->get(['id', 'name', 'custom_name', 'lang_name', 'route', 'parent_route', 'is_admin', 'is_teacher', 'is_student', 'is_parent']);

        /* One entry per route: the variant already meant for this role wins, otherwise the
           oldest, so adding it does not flag a row that belongs to a different role. */
        $menus = $menus->groupBy('route')->map(function ($variants) use ($flag) {
            return $variants->sortByDesc(function ($permission) use ($flag) {
                return [(int) $permission->{$flag}, -$permission->id];
            })->first();
        })->values();

        /* Qualify a name only where it is shared, and only as far as needed. The parent menu
           is the friendlier qualifier, but only when the parents print differently - two
           "Apply Leave" entries both sit under something labelled "Leave", so there the route
           is the only thing that tells them apart. */
        $parentLabels = [];
        foreach ($menus->pluck('parent_route')->filter()->unique() as $parentRoute) {
            $parent = Permission::where('route', $parentRoute)->first();
            $parentLabels[$parentRoute] = $parent ? menuLabel($parent) : null;
        }

        $groups = $menus->groupBy(function ($permission) { return menuLabel($permission); });

        return $menus->map(function ($permission) use ($groups, $parentLabels) {
            $label = menuLabel($permission);
            $permission->display_label = $label;

            $siblings = $groups[$label];
            if ($siblings->count() < 2) {
                return $permission;
            }

            $siblingParentLabels = $siblings->map(function ($sibling) use ($parentLabels) {
                return $parentLabels[$sibling->parent_route] ?? null;
            });

            $parentsAreDistinct = $siblingParentLabels->filter()->count() === $siblings->count()
                && $siblingParentLabels->unique()->count() === $siblings->count();

            $permission->display_label = $label . ' — ' . ($parentsAreDistinct
                ? $parentLabels[$permission->parent_route]
                : $permission->route);

            return $permission;
        })->sortBy('display_label', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function sectionStore(SectionRequestFrom $request)
    {     
     
        if (config('app.app_sync')) {
            Toastr::error('Restricted in demo mode');
            return back();
        }
        try{
            /* A slug, and one nothing else is using. The route column is this
               module's identifier for a menu, and strtolower() on the name left
               spaces in it - "Student Affairs" became the route "student affairs" -
               while two sections of the same name quietly took the same route as
               each other. menuCreate() already does it this way. */
            $base = \Illuminate\Support\Str::slug($request->name) ?: 'section';
            $route = $base;
            $suffix = 2;
            while (Permission::where('route', $route)->exists()) {
                $route = $base . '-' . $suffix++;
            }

            /* A position, not an id. This read the newest row's id and used it as a
               sort order - 1856 against a real maximum of 6009 - so a new section
               landed in the middle of the sidebar rather than at the end, for no
               reason anybody could see. */
            $position = (int) Permission::max('position') + 1;

            $permission = Permission::create([
                'name' => $request->name,
                'route' => $route,
                'position' => $position,
                'user_id' => null,
                'permission_section'=>1,
                'type'=>1,
                'is_menu' => 1,
                'status' => 1,
                'menu_status' => 1,
                'is_admin'=>!in_array(auth()->user()->role_id, [2,3,GlobalVariable::isAlumni()]) ? 1 : 0,
                'is_student'=>auth()->user()->role_id == 2 ? 1 : 0,
                'is_parent'=>auth()->user()->role_id == 3 ? 1 : 0,
            ]);
            Sidebar::create([
                'permission_id' => $permission->id,
                'user_id' => null,
                'role_id' => sidebarRoleId(),
                'position' => $position,
                'active_status' => 1,
                'parent'=>NULL
            ]);
            Cache::forget(sidebarCacheKey());
            
            Toastr::success('Operation successful', 'Success');
            return redirect()->route('menumanage.index');
        }catch(\Exception $e){
            /* This swallowed everything and then returned nothing at all, which in
               Laravel is a blank 200 - the button was pressed, the page went white,
               and no section appeared. */
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error(__('common.Operation failed'), 'Failed');
            return redirect()->route('menumanage.index');
        }
    }
    public function sectionEditForm($id)
    {
        if (config('app.app_sync')) {
            Toastr::error('Restricted in demo mode');
            return back();
        }
        $data = [];
        $data['unused_menus'] = self::unUsedMenu(); 
        Cache::forget(sidebarCacheKey());             
        $data['sidebar_menus'] = sidebar_menus();
        $data['editPermissionSection'] = Permission::whereNull('user_id')->where('id', $id)->first();
        return view('menumanage::index', $data);
    }

    public function sectionUpdate(SectionRequestFrom $request)
    {        
        $request->validate([
            'id' => 'required'
        ]);
        $section = Permission::find($request->id);
        $section->name = $request->name;
        $section->save();

        /* The only write in this controller that cleared nothing, so a renamed section kept
           its old heading for every role until something else happened to clear the key. The
           name lives on the permission row, which every role's tree reads, so every role's
           cache goes - the same reasoning as menuRename. */
        forgetAllSidebarCaches();

        Toastr::success('Operation successful', 'Success');
        return redirect()->route('menumanage.index');
    }
    public function deleteSection(Request $request)
    {
        //   uest->all());
       
        if(config('app.app_sync')) {
            return $this->reloadWithData();
        }
      
        try {
            if ($request->id != 1) {
                $section = Sidebar::where('id', $request->id)->whereNull('user_id')->first();
                if (count($section->subModule)!=0) {
                   
                    foreach ($section->subModule as $sidebar) {
                        $sidebar->update(['active_status' => 0, 'ignore'=>0]);                      
                    }
                }
               
                if($section->permissionInfo->permission_section == 1 && count($section->subModule)==0) {
                  
                    Permission::whereNull('user_id')->where('id', $section->permission_id)->delete();
                    $section->delete();
                }
                              
            }
            Cache::forget(sidebarCacheKey());
            return $this->reloadWithData();
        } catch (\Exception $e) {
            return response()->json([
                'msg' => __('common.Operation failed')
            ], 500);
        }

    }

    public function removeMenu(Request $request)
    {
        $sidebar = Sidebar::with('userChildMenu')->where('id', $request->id)->whereNull('user_id')->first();

        if ($sidebar && !config('app.app_sync')) {
            /* A menu that still holds entries is not removed, and nothing inside it
               is touched.

               Removing one used to take everything inside it out of the sidebar as
               well: one click on a dropdown pulled every menu under it out too, and
               each had to be dragged back in from Available one at a time. The
               entries under a menu are not this button's to take away as a side
               effect of tidying the line above them - so the removal is refused and
               whoever wants the menu gone empties it first, deliberately. */
            $activeChildren = Sidebar::whereNull('user_id')
                ->where('role_id', $sidebar->role_id)
                ->where('parent', $sidebar->permission_id)
                ->where('active_status', 1)
                ->count();

            if ($activeChildren > 0) {
                return response()->json([
                    'msg' => __('menumanage::menuManage.empty_menu_before_remove', ['count' => $activeChildren]),
                ], 422);
            }

            $sidebar->active_status = 0;
            $sidebar->save();
        }

        Cache::forget(sidebarCacheKey());
        return $this->reloadWithData();

    }

    /**
     * Deletes a self-made menu for good, from the Available list.
     *
     * removeMenu() only sets active_status to 0, which is what puts an entry in
     * that list - so a menu someone created and then thought better of sat there
     * for ever with no way to be rid of it. This is that way, and it is deliberately
     * narrow: only a menu carrying is_custom, meaning one made in this manager (see
     * menuCreate). Everything else in the list ships with the system or arrives with
     * a module and has a screen behind it, so deleting its permission row would take
     * a working part of the system from every role at once, with nothing to put it
     * back short of a reinstall. Sitting unused is already how those are hidden.
     *
     * Wider than the manager's other actions, which each edit one role's layout: a
     * permission belongs to the school, so this removes the menu from every role.
     * The confirmation says so.
     */
    public function deleteMenu(Request $request)
    {
        if (config('app.app_sync')) {
            return $this->reloadWithData();
        }

        $request->validate(['id' => ['required', 'integer']]);

        $sidebar = Sidebar::with('permissionInfo')->where('id', $request->id)->whereNull('user_id')->first();
        $permission = optional($sidebar)->permissionInfo;

        if (!$sidebar || !$permission) {
            return response()->json(['msg' => __('menumanage::menuManage.menu_not_found')], 422);
        }

        if (!$permission->is_custom) {
            return response()->json(['msg' => __('menumanage::menuManage.only_custom_menu_deletable')], 422);
        }

        /* No "it is still in use somewhere" refusal.

           That check was here, and it was a dead end: a menu made in this manager is
           placed live in every eligible role's layout the moment it is created, so it
           was always in use somewhere, and nothing on screen said which role to go
           and clear it from first. The entry could not be deleted from the one place
           that offers to delete it.

           The deletion is school-wide either way, and the confirmation says so before
           anything happens. That is the consent; a block on top of it only stopped
           people doing what they had already agreed to. */

        /* A created menu is a dropdown, so it can hold entries. Deleting it while
           something sits inside would leave those pointing at a parent that is gone
           and drop them out of every layout silently. Emptying it first is the
           person's decision to make, not this method's. */
        $childCount = Sidebar::where('parent', $permission->id)->count();
        if ($childCount > 0) {
            return response()->json([
                'msg' => __('menumanage::menuManage.empty_menu_before_delete', ['count' => $childCount]),
            ], 422);
        }

        try {
            DB::transaction(function () use ($permission) {
                /* Every role's layout, not just the one being arranged - the row being
                   deleted is the school's, and a copy left behind in another role's
                   layout would render a menu whose permission no longer exists. */
                Sidebar::where('permission_id', $permission->id)->delete();
                DB::table('assign_permissions')->where('permission_id', $permission->id)->delete();
                $permission->delete();
            });
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['msg' => __('common.Operation failed')], 500);
        }

        Log::warning('Custom sidebar menu deleted', [
            'permission_id' => $permission->id,
            'name' => $permission->name,
            'route' => $permission->route,
            'by_user_id' => auth()->id(),
        ]);

        $this->forgetSidebarCaches();

        return $this->reloadWithData();
    }


    /**
     * The roles a write applies to.
     *
     * The one being arranged, always, plus any the person has asked to carry the
     * change across to. Empty means the arranged role alone - so nothing reaches
     * another role's sidebar unless it was asked for by name.
     */
    private function targetRoleIds(Request $request)
    {
        $arranged = (int) sidebarRoleId();

        $requested = collect((array) $request->input('apply_roles', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        if (empty($requested)) {
            return [$arranged];
        }

        /* The arranged role is never dropped: the layout on screen is its layout,
           and leaving it out would save the arrangement everywhere except where it
           was made. */
        return array_values(array_unique(array_merge([$arranged], $requested)));
    }

    /**
     * Sidebar row id to permission id, for the rows the browser posted.
     *
     * The browser knows the arranged role's row ids and nothing else - a row id is
     * per role, so role 1's "Assign Program" is row 335 and role 7's is row 679.
     * The permission behind them is the same for every role, and so is `parent`,
     * which holds a permission id rather than a row id. Translating through the
     * permission is therefore what lets one arrangement be written to several
     * layouts.
     */
    private function permissionsForRows(array $rowIds)
    {
        if (empty($rowIds)) {
            return [];
        }

        return Sidebar::whereIn('id', $rowIds)->whereNull('user_id')
            ->pluck('permission_id', 'id')->all();
    }

    /** Every row id in a nested order tree, parents and children alike. */
    private function rowIdsIn($menuItems)
    {
        $ids = [];

        foreach ((array) $menuItems as $item) {
            if (isset($item->id)) {
                $ids[] = $item->id;
            }
            if (isset($item->children)) {
                $ids = array_merge($ids, $this->rowIdsIn($item->children));
            }
        }

        return $ids;
    }

    private function orderMenu(array $menuItems, $menu_status = 1, $parent_id = null, $un_used = null, $roleId = null, array $permissionByRow = [])
    {
        $roleId = $roleId ?? sidebarRoleId();

        foreach ($menuItems as $index => $item) {
            /* Matched on the permission in this role's own layout, not on the row id
               the browser sent - that id belongs to the role being arranged. */
            $permissionId = $permissionByRow[$item->id] ?? null;

            if (!$permissionId) {
                continue;
            }

            $menuItem = Sidebar::where('permission_id', $permissionId)
            ->where('role_id', $roleId)
            ->when(!$un_used, function($q){
                $q->where('active_status', 1);
              })
            ->whereNull('user_id')->first();

            $data = [
                'position' => $index + 1,
                'parent'=>$parent_id,
                'active_status' => $menu_status ?? 1,
            ];

            if ($menuItem) {
                $menuItem->update($data);
                if (isset($item->children)) {
                    $this->orderMenu($item->children, $menu_status, $permissionId, $un_used, $roleId, $permissionByRow);
                }
            }

        }

    }


    public function menuUpdate(Request $request)
    {
        $menuItemOrder = json_decode($request->get('order')) ?? [];
        $roleIds = $this->targetRoleIds($request);

        /* One translation table for the whole write, built from every row the
           browser named - the tree it dragged, and the two lists of what is in use
           and what is not. */
        $permissionByRow = $this->permissionsForRows(array_merge(
            $this->rowIdsIn($menuItemOrder),
            (array) $request->input('ids', []),
            (array) $request->input('unused_ids', [])
        ));

        $permissionsOf = fn ($rowIds) => collect((array) $rowIds)
            ->map(fn ($id) => $permissionByRow[$id] ?? null)->filter()->unique()->values()->all();

        if (!config('app.app_sync')) {
            $unusedPermissions = $permissionsOf($request->input('unused_ids', []));
            $usedPermissions = $permissionsOf($request->input('ids', []));

            foreach ($roleIds as $roleId) {
                /* A role that was never eligible for a menu has no row for it, and
                   none is created here: carrying an arrangement across should move
                   what a role already has, not hand it menus it was never meant to
                   see. Those simply find nothing to update. */
                if ($unusedPermissions) {
                    Sidebar::whereIn('permission_id', $unusedPermissions)
                        ->where('role_id', $roleId)->whereNull('user_id')
                        ->update(['active_status' => 0]);
                }
                if ($usedPermissions) {
                    Sidebar::whereIn('permission_id', $usedPermissions)
                        ->where('role_id', $roleId)->whereNull('user_id')
                        ->update(['active_status' => 1]);
                }
            }
        }

        foreach ($roleIds as $roleId) {
            $this->orderMenu($menuItemOrder, $request->menu_status, $request->section, $request->un_used, $roleId, $permissionByRow);
        }

        /* Every role written to, not only the one on screen. */
        foreach ($roleIds as $roleId) {
            Cache::forget('sidebars_role' . $roleId);
        }

        return $this->reloadWithData();
    }


    public function sortSection(Request $request)
    {
        
        $roleIds = $this->targetRoleIds($request);
        $permissionByRow = $this->permissionsForRows((array) $request->input('ids', []));

        if($request->ids && !config('app.app_sync')) {
            foreach ($roleIds as $roleId) {
                foreach($request->ids as $key=>$rowId) {
                    /* Through the permission, so the same order of sections can be
                       written to a role whose own row ids are different. */
                    $permissionId = $permissionByRow[$rowId] ?? null;

                    if (!$permissionId) {
                        continue;
                    }

                    $sidebar = Sidebar::where('permission_id', $permissionId)->whereNull('user_id')
                    ->where('role_id', $roleId)
                    ->where('active_status', 1)->first();
                    if($sidebar){
                      $sidebar->position = $key+1;
                      $sidebar->save();
                    }
                }
            }
        }

        foreach ($roleIds as $roleId) {
            Cache::forget('sidebars_role' . $roleId);
        }

        /* The panels, as every other write here returns. This returned nothing, so
           the browser handed `undefined` to reloadAfterChange(), which then wrote it
           into all three panels and emptied them - the arrangement looked lost the
           moment a section was dragged. */
        return $this->reloadWithData();
    }

    public function resetMenu()
    {        
        try {          
            Sidebar::whereNull('user_id')
            ->where('role_id', sidebarRoleId())
            ->delete();
            $this->resetSidebarStore();
            Cache::forget(sidebarCacheKey());  
            return redirect()->back();

        } catch (\Exception $e) {
            /* Both resets used to swallow the failure and return nothing, so a reset
               that had wiped the layout and then failed to rebuild it showed a white
               page - with the sidebar gone and no word of why. */
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error(__('common.Operation failed'), 'Failed');
            return redirect()->back();
        }

    }
    public function resetWithDefault()
    {
        try {

            Sidebar::whereNull('user_id')->where('role_id', sidebarRoleId())->delete();
            $this->defaultSidebarStore();
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error(__('common.Operation failed'), 'Failed');
            return redirect()->back();
        }
    }

    private function reloadWithData()
    {

        $data = $this->getMenusData();
        return response()->json([
            'msg' => 'Success',
            'available_list' => (string)view('menumanage::components.available_list', $data),
            'menus' => (string)view('menumanage::components.components', $data),
            'live_preview' => (string)view('menumanage::components.live_preview', $data)
        ], 200);
    }
   
    public function getMenusData()
    {      
        $unused_menus = self::unUsedMenu();              
        $sidebar_menus = sidebar_menus();
        return compact('unused_menus', 'sidebar_menus');
    }
    public static function unUsedMenu()
    {
        $sectionIds = Sidebar::whereNull('parent')->pluck('permission_id')->toArray();
        $parentSidebars = Sidebar::whereIn('parent', $sectionIds)    
                        ->deActiveMenuUser()
                        ->pluck('permission_id')
                        ->toArray();
        $single = Sidebar::whereNotIn('parent', $parentSidebars)
                    ->deActiveMenuUser()
                    ->pluck('permission_id')
                    ->toArray();       
        $hasIds = array_merge($parentSidebars, $single);
       
        $hasIds = (array_unique($hasIds));
        if($hasIds) {
           return Sidebar::whereIn('permission_id', $hasIds)->deActiveMenuUser()->get();
        } 
        return collect();  
    }
}
