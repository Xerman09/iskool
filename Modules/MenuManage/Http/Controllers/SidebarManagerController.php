<?php

namespace Modules\MenuManage\Http\Controllers;

use App\GlobalVariable;
use Illuminate\Http\Request;
use App\Traits\SidebarDataStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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
        ]);

        $roleId = sidebarRoleId();
        $permission = Permission::findOrFail($request->permission_id);

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
        $permission_position = Permission::whereNotNull('permission_section')->whereNull('user_id')->latest()->first();
        try{
            $permission = Permission::create([
                'name' => $request->name,
                'route'=>strtolower($request->name),
                'position' => $permission_position->id + 1,
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
                'position' => $permission_position->id + 1,
                'active_status' => 1,
                'parent'=>NULL
            ]);
            Cache::forget(sidebarCacheKey());
            
            Toastr::success('Operation successful', 'Success');
            return redirect()->route('menumanage.index');
        }catch(\Exception $e){

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
            if($sidebar->userChildMenu->count() > 0) {
                foreach($sidebar->userChildMenu as $child) {
                    $child->update(['active_status'=>0]);
                }
               
            }
            Sidebar::where('parent', $sidebar->permission_id)->update(['active_status'=>0]);
            $sidebar->active_status = 0;
            $sidebar->save();
        }
        Cache::forget(sidebarCacheKey());
        return $this->reloadWithData();

    }


    private function orderMenu(array $menuItems,  $menu_status = 1, $parent_id = null, $un_used = null)
    { 
        
        foreach ($menuItems as $index => $item) {
            $menuItem = Sidebar::where('id', $item->id)
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
                    $this->orderMenu($item->children, $menu_status, $menuItem->permission_id, $un_used);
                }
            }

        }

    }


    public function menuUpdate(Request $request)
    {
      
        if (!config('app.app_sync')) {        
            $menuItemOrder = json_decode($request->get('order'));

            if ($request->unused_ids) {
                Sidebar::whereIn('id', $request->unused_ids)->update([
                    'active_status' => 0, 
                ]);
            }
            if ($request->ids) {
                Sidebar::whereIn('id', $request->ids)->update([
                    'active_status' => 1,
                ]);
            }
        
        }
        $this->orderMenu($menuItemOrder, $request->menu_status, $request->section, $request->un_used);        
      
        Cache::forget(sidebarCacheKey());
        return $this->reloadWithData();
    }


    public function sortSection(Request $request)
    {
        
        if($request->ids && !config('app.app_sync')) {
            foreach($request->ids as $key=>$permissionSection) {
              
              $sidebar =  Sidebar::where('id', $permissionSection)->whereNull('user_id')->where('role_id', sidebarRoleId())
              ->where('active_status', 1)->first();
              if($sidebar){
                $sidebar->position = $key+1;
                $sidebar->save();
              }
             
            }
        }
        Cache::forget(sidebarCacheKey()); 
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
           ;
        }

    }
    public function resetWithDefault()
    {
        try {
                    
            Sidebar::whereNull('user_id')->where('role_id', sidebarRoleId())->delete();
            $this->defaultSidebarStore();  
            return redirect()->back();           
        } catch (\Exception $e) {
           ;
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
