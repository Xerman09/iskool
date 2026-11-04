<?php

use App\GlobalVariable;
use App\SmClass;
use App\SmSection;
use App\SmStudent;
use App\SmSubject;
use App\SmCurrency;
use App\Models\Theme;
use App\SmClassSection;
use App\SmAssignSubject;
use App\Models\StudentRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\Lms\Entities\ModuleStatus;
use Modules\MenuManage\Entities\Sidebar;
use Modules\RolePermission\Entities\Permission;
use Modules\MenuManage\Entities\AlternativeModule;
use App\Traits\SidebarDataStore;

if (!function_exists('color_theme')) {
    function color_theme()
    {
        if (!auth()->check()) {
            return userColorThemeActive();
        } else if (auth()->user()) {
            return userColorThemeActive(auth()->user()->id);
        }

    }

}

if (!function_exists('userColorThemeActive')) {
    function userColorThemeActive(int $user_id = null)
    {
        $school_id = auth()->user()->school_id ?? 1;
        $cache_key = $user_id ? ('active_theme_user_' . $user_id) : 'active_theme_school_' . $school_id;
        $active_theme = Cache::rememberForever($cache_key, function () use ($user_id) {
            $theme = Theme::with('colors')->where('is_default', 1)
                ->when($user_id, function ($q) use ($user_id) {
                    $q->where('created_by', $user_id);
                })->first();
            if ($user_id && !$theme) {
                $theme = Theme::with('colors')->where('is_default', 1)->first();
            }
            if (!$theme) {
                $theme = Theme::with('colors')->first();
            }
            return $theme;
        });
        return $active_theme;
    }
}

if (!function_exists('userColorThemes')) {
    function userColorThemes(int $user_id = null)
    {

        $themes = Theme::with('colors')
            ->when($user_id, function ($q) use ($user_id) {
                $q->where('created_by', $user_id);
            })->get();
        if ($user_id && !$themes) {
            $themes = Theme::with('colors')->where('is_system', 1)->get();
        }
        return $themes;
    }
}

if (!function_exists('activeStyle')) {
    function activeStyle()
    {
        if (session()->has('active_style') && session()->get('active_style')) {
            $active_style = session()->get('active_style');
            return $active_style;
        } else {
            $active_style = auth()->check() ? Theme::where('id', auth()->user()->style_id)->first() :
                Theme::where('school_id', 1)->where('is_default', 1)->first();
            if ($active_style == null) {
                $active_style = Theme::where('school_id', 1)->where('is_default', 1)->first();
            }
            if($active_style == null){
                $active_style = Theme::first();
            }

            session()->put('active_style', $active_style);
            return session()->get('active_style');
        }
    }
}

if (!function_exists('currency_format_list')) {
    function currency_format_list()
    {
        $symbol = generalSetting()->currency_symbol ?? '$';

        $code = generalSetting()->currency ?? 'USD';
        $formats = [
            ['name' => 'symbol_amount', 'format' => 'symbol(amount) =  ' . $symbol . ' 1'],
            ['name' => 'amount_symbol', 'format' => 'amount(symbol) = 1' . $symbol],
            ['name' => 'code_amount', 'format' => 'code(amount) = ' . $code . ' 1'],
            ['name' => 'amount_code', 'format' => 'amount(code) = 1 ' . $code],
        ];

        return $formats;
    }
}
if (!function_exists('currency_format')) {
    function currency_format($amount = null, string $format = null)
    {
        if (!$amount) return false;
        $format = generalSetting()->currencyDetail;
        if (!$format) return $amount;

        $decimal = $format->decimal_digit ?? 0;
        $decimal_separator = $format->decimal_separator ?? "";
        $thousands_separator = $format->thousand_separator ?? "";
        $amount = number_format($amount, $decimal, $decimal_separator, $thousands_separator);
        $symbolCode = $format->currency_type == 'C' ? $format->code : $format->symbol;

        $symbolCodeSpace = $format->space ?
            ($format->currency_position == 'S' ? $symbolCode . ' ' : ' ' . $symbolCode) : $symbolCode;

        if ($format->currency_position == 'S') {
            return $symbolCodeSpace . $amount;
        } elseif ($format->currency_position == 'P') {
            return $amount . $symbolCodeSpace;
        }
    }
}
if (!function_exists('classes')) {
    function classes(int $academic_year = null)
    {
        return SmClass::withOutGlobalScopes()
            ->when($academic_year, function ($q) use ($academic_year) {
                $q->where('academic_id', $academic_year);
            }, function ($q) {
                $q->where('academic_id', getAcademicId());
            })->where('school_id', auth()->user()->school_id)
            ->where('active_status', 1)->get();
    }
}
if (!function_exists('sections')) {
    function sections($class_id = null, $academic_year = null)
    {
        if (!$class_id) return null;
        return SmClassSection::withOutGlobalScopes()->where('class_id', $class_id)
            ->where('school_id', auth()->user()->school_id)
            ->when($academic_year, function ($q) use ($academic_year) {
                $q->where('academic_id', $academic_year);
            }, function ($q) {
                $q->where('academic_id', getAcademicId());
            })->get();

    }
}
if (!function_exists('subjects')) {
    function subjects(int $class_id, int $section_id, int $academic_year = null)
    {
        $subjects = SmAssignSubject::withOutGlobalScopes()
            ->where('class_id', $class_id)
            ->where('section_id', $section_id)
            ->where('school_id', auth()->user()->school_id)
            ->when($academic_year, function ($q) use ($academic_year) {
                $q->where('academic_id', $academic_year);
            }, function ($q) {
                $q->where('academic_id', getAcademicId());
            })->select('class_id', 'section_id', 'subject_id')->distinct(['class_id', 'section_id', 'subject_id'])->get();

        return $subjects;

    }
}
if (!function_exists('students')) {
    function students(int $class_id, int $section_id = null, int $academic_year = null)
    {
        $student_ids = StudentRecord::where('class_id', $class_id)
            ->when($section_id, function ($q) use ($section_id) {
                $q->where('section_id', $section_id);
            })->when('academic_year', function ($q) use ($academic_year) {
                $q->where('academic_id', $academic_year);
            })->where('school_id', auth()->user()->school_id)->pluck('student_id')->unique()->toArray();

        $students = SmStudent::withOutGlobalScopes()->whereIn('id', $student_ids)->get();

        return $students;

    }
}
if (!function_exists('courses')) {
    function courses()
    {
        return \App\Course::selectable()->where('school_id', auth()->user()->school_id)->get();
    }
}
if (!function_exists('semesters')) {
    function semesters()
    {
        return \App\Semester::where('school_id', auth()->user()->school_id)->where('active_status', 1)->get();
    }
}
if (!function_exists('classSubjects')) {
    function classSubjects($class_id = null)
    {
        $subjects = SmAssignSubject::query();
        if (teacherAccess()) {
            $subjects->where('teacher_id', auth()->user()->staff->id);
        }
        if ($class_id != "all_class") {
            $subjects->where('class_id', '=', $class_id);
        } else {
            $subjects->distinct('class_id');
        }
        $subjectIds = $subjects->distinct('subject_id')->get()->pluck(['subject_id'])->toArray();

        return SmSubject::whereIn('id', $subjectIds)->get(['id', 'subject_name']);
    }
}
if (!function_exists('subjectSections')) {
    function subjectSections($class_id = null, $subject_id = null)
    {

        if (!$class_id || !$subject_id) return null;

        $sectionIds = SmAssignSubject::where('class_id', $class_id)
            ->where('subject_id', '=', $subject_id)
            ->where('school_id', auth()->user()->school_id)
            ->when(teacherAccess(), function ($q) {
                $q->where('teacher_id', auth()->user()->staff->id);
            })
            ->distinct(['class_id', 'section_id'])
            ->pluck('section_id')
            ->toArray();
        return SmSection::whereIn('id', $sectionIds)->get(['id', 'section_name']);

    }
}

if (!function_exists('routeIsExist')) {
    function routeIsExist($route, $children_id = null)
    {
        if ($children_id) {
            if (Route::has($route)) {
                return true;
            }
        }
        if (Route::has($route)) {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('validRouteUrl')) {
    function validRouteUrl($route, $children_id = null)
    {
        $url = null;
        try {
            if (routeIsExist($route, $children_id)) {
                if ($children_id) {
                    $url = \route($route, $children_id);
                } else {
                    $url = \route($route);
                }

            }
        } catch (\Exception $e) {
        }
        return $url;
    }
}

if (!function_exists('routeIs')) {
    function routeIs($route)
    {
        if (Route::currentRouteName() == $route) {
            return true;
        } else {
            return false;
        }
    }
}
if (!function_exists('subModuleRoute')) {
    function subModuleRoute($menu, $routes = [])
    {
        if (@$menu->permissionInfo->route) {
            $routes[] = $menu->permissionInfo->route;
        }
        if ($menu->subModule->count()) {
            foreach ($menu->subModule as $child) {
                $routes = subModuleRoute($child, $routes);
            }
            return $routes;
        }
        return $routes;
    }
}
if (!function_exists('deActivePermissions')) {
    function deActivePermissions()
    {
        $alternativeDeActiveModuleInfo = AlternativeModule::where('status', 0)->pluck('module_name')->toArray();
        return Permission::whereIn('module', $alternativeDeActiveModuleInfo)->pluck('id')->toArray();

    }
}
if (!function_exists('sidebar_menus')) {
    function sidebar_menus()
    {
        $user = auth()->user();

        /* Ten minutes, not forever. The Sidebar Manager clears this key on every write, so
           an edit still shows at once; the expiry is for the case no write can cover - a
           deploy. A tree cached before an update to this query, or to the layout rows it
           reads, was served unchanged afterwards for good, which is why a pulled fix looked
           like it had not arrived. It now heals on its own rather than needing cache:clear,
           and it covers any future invalidation path someone forgets to add. */
        return $sidebars = Cache::remember(sidebarCacheKey($user), now()->addMinutes(10), function () use ($user) {
            return Sidebar::with(['subModule', 'permissionInfo'])
                ->whereNull('parent')
                ->whereHas('permissionInfo', function ($q) use ($user) {
                    $q->where('menu_status', 1)
                        ->when(sidebarRoleId($user) == 2 || sidebarRoleId($user) == GlobalVariable::isAlumni(), function ($q) {
                            $q->where('is_student', 1);
                        })->when(sidebarRoleId($user) == 3, function ($q) {
                            $q->where('is_parent', 1);
                        })->when(!in_array(sidebarRoleId($user), [2, 3, GlobalVariable::isAlumni()]), function ($q) {
                            /* is_admin OR is_teacher, the same pair Sidebar::subModule()
                               accepts for children. The Sidebar Manager stamps is_teacher
                               when a menu is added to a teacher's layout, so requiring
                               is_admin here meant such a menu rendered as a child of a
                               section but vanished if it sat at the top level - added,
                               saved, confirmed, and still not there. */
                            $q->where(function ($q2) {
                                $q2->where('is_admin', 1)->orWhere('is_teacher', 1);
                            });
                        });
                })
                /* The role template, not a per-account copy - see sidebarRoleId(). */
                ->whereNull('user_id')->where('role_id', sidebarRoleId($user))->where('active_status', 1)
                ->orderBy('position', 'ASC')->get();
        });
    }
}

if (!function_exists('storePermissionData')) {
    function storePermissionData($permission, $user_id = null, $school_id = null)
    {
        Permission::updateOrCreate([
            'module' => $permission['module'],
            'sidebar_menu' => $permission['sidebar_menu'],
            'lang_name' => $permission['lang_name'],
            'icon' => $permission['icon'],
            'svg' => $permission['svg'],
            'route' => $permission['route'],
            'parent_route' => $permission['parent_route'],
            'is_admin' => $permission['is_admin'],
            'is_teacher' => $permission['is_teacher'],
            'is_student' => $permission['is_student'],
            'is_parent' => $permission['is_parent'],

            'is_saas' => $permission['is_saas'],
            'is_menu' => $permission['is_menu'],
            'status' => $permission['status'],
            'menu_status' => $permission['menu_status'],
            'relate_to_child' => $permission['relate_to_child'],
            'alternate_module' => $permission['alternate_module'],
            'permission_section' => $permission['permission_section'],
            'type' => $permission['type'],
            'user_id' => $permission['permission_section'] == 1 && $user_id ? $user_id : null,
            'old_id' => $permission['old_id'],
            'school_id' => $school_id ?? 1,
        ],
            [
                'name' => $permission['name'],
                'position' => $permission['position'],

            ]);

        if (isset($permission['child'])) {
            foreach ($permission['child'] as $child) {
                storePermissionData($child);
            }
        }
    }
}

if (!function_exists('sidebarPermission')) {
    function sidebarPermission($permission)
    {
        if (!$permission) return false;
        $user = auth()->user();
        if ($permission->permission_section == 1) return true;

        if ($permission->module && $permission->module != 'fees_collection') {
            if (moduleStatusCheck($permission->module)) {
                $access = true;
                if ($permission->module == 'Saas') {
                    $saasNotAdministrator = ['administrator-notice', 'school/ticket-view', 'subscription/history'];
                    $subscriptions = ['subscription/package-list', 'subscription/history'];
                    $access = true;
                    if ($permission->route == 'saas.custom-domain') {
                        $access = config('app.allow_custom_domain') ? true : false;
                    }
                    if ($permission->route == 'school-general-settings') {
                        $access = isSubscriptionEnabled() && $user->is_administrator == 'yes' ? true : false;
                    }
                    if (in_array($permission->route, $saasNotAdministrator)) {
                        $access = $user->is_administrator != 'yes' ? true : false;
                    }
                    if (in_array($permission->route, $subscriptions)) {
                        $access = isSubscriptionEnabled() && $user->is_administrator != 'yes' ? true : false;
                    }
                }
            } else {
                $access = false;
            }

        } elseif (!$permission->module) {
            $access = true;
        }

        if ($permission->module == 'fees_collection') {
            $routeList = ['fees_group', 'fees_type', 'search_fees_due', 'fees_forward'];
            if ((int)generalSetting()->fees_status != 1 && directFees()) {
                $access = true;
                if (in_array($permission->route, $routeList)) {
                    $access = false;
                }
            } elseif ((int)generalSetting()->fees_status != 1 && directFees() == false) {
                $access = true;
                if (in_array($permission->route, $routeList)) {
                    $access = true;
                }
            } else {
                $access = false;
            }


        }
        if ($permission->module == 'Fees') {
            if ((int)generalSetting()->fees_status == 1) {
                $access = true;
                if (moduleStatusCheck('Saas') && $permission->sidebar_menu) {
                    $access = isMenuAllowToShow($permission->sidebar_menu);
                }
            } else {
                $access = false;
            }

        }
        if ($permission->alternate_module == 'OnlineExam') {
            if (moduleStatusCheck('OnlineExam')) {
                if ($permission->route != 'online_exam' && $permission->alternate_module == 'OnlineExam') {
                    $access = false;
                }
            }
        }
        if ($permission->alternate_module == 'University') {
            if (moduleStatusCheck('University')) {
                if ($permission->alternate_module == 'University') {
                    $access = false;
                }
            }
        }
        if (moduleStatusCheck('Saas') && $permission->sidebar_menu) {
            if (!$permission->module || $permission->alternate_module == 'OnlineExam') {
                $access = isMenuAllowToShow($permission->sidebar_menu);
            }

        }

        if (userPermission($permission->route) == true && $access == true) {
            return true;
        }
        return false;
    }
}

if (!function_exists('ignorePermissionRoutes')) {
    function ignorePermissionRoutes()
    {
        return ['reports', 'system_settings', 'front_settings', 'fees.fees-report', 'exam-setting'];
    }
}
if (!function_exists('ignorePermissionIds')) {
    function ignorePermissionIds()
    {
        return Permission::whereIn('route', ignorePermissionRoutes())->pluck('id')->toArray();
    }
}

function hasDueFees($children_id)
{
    $student_ids = Cache::get('have_due_fees_' . auth()->user()->id);

    return $student_ids && in_array($children_id, $student_ids);


}
if (!function_exists('menuLabel')) {
    /**
     * The one place a sidebar entry is turned into text.
     *
     * A superadmin label typed in the Sidebar Manager wins; otherwise the shipped phrase,
     * which the sidebar reads through __() because lang_name holds a translation key, not
     * text. Sections carry a plain name and no key, so those fall through to name. The
     * manager and the live sidebar both call this, so what you type is what you see in
     * both - they used to disagree, name in the manager against __(lang_name) live.
     */
    function menuLabel($permission)
    {
        if (!$permission) {
            return '';
        }

        if (filled($permission->custom_name)) {
            return $permission->custom_name;
        }

        if (filled($permission->lang_name)) {
            $translated = __($permission->lang_name);

            /* __() hands back the key itself when nothing is registered for it, and hands back a
               whole array when the key names a group or a file - a label is neither. */
            if (is_string($translated) && $translated !== $permission->lang_name) {
                return $translated;
            }
        }

        return (string) $permission->name;
    }
}

if (!function_exists('menuDefaultLabel')) {
    /** The label a sidebar entry shipped with, ignoring any superadmin rename. */
    function menuDefaultLabel($permission)
    {
        if (!$permission) {
            return '';
        }

        $shipped = clone $permission;
        $shipped->custom_name = null;

        return menuLabel($shipped);
    }
}

if (!function_exists('sidebarRoleId')) {
    /**
     * Sidebar layouts are shared by role, not owned by one account.
     *
     * The sidebars table still carries a user_id column; a row with user_id NULL is the
     * template for its role_id, which is what every user of that role is served. Before
     * this, the Sidebar Manager wrote rows stamped with the editor's own id, so an admin
     * who regrouped the menu changed it for nobody but themselves.
     */
    function sidebarRoleId($user = null)
    {
        /* The Sidebar Manager aims at one role at a time; everywhere else this is just
           the viewer's own role. */
        if (\Modules\MenuManage\Entities\Sidebar::$layoutRoleId !== null) {
            return (int) \Modules\MenuManage\Entities\Sidebar::$layoutRoleId;
        }

        $user = $user ?: (auth()->check() ? auth()->user() : null);

        return $user ? (int) $user->role_id : 0;
    }
}

if (!function_exists('sidebarCacheKey')) {
    /** One cached tree per role, so a change reaches every account of that role at once. */
    function sidebarCacheKey($user = null)
    {
        return 'sidebars_role' . sidebarRoleId($user);
    }
}

if (!function_exists('forgetAllSidebarCaches')) {
    /** For changes that are school-wide, such as renaming a menu entry. */
    function forgetAllSidebarCaches()
    {
        $roleIds = \Illuminate\Support\Facades\DB::table('roles')->pluck('id')->all();

        foreach (array_unique(array_merge($roleIds, [0])) as $roleId) {
            \Illuminate\Support\Facades\Cache::forget('sidebars_role' . $roleId);
        }
    }
}

if (!function_exists('menuIconChoices')) {
    /**
     * The icon classes this installation already uses, for the menu icon picker.
     *
     * Read from the menus themselves rather than from a bundled icon font, so what a
     * new menu can be given is exactly what the rest of the sidebar is drawn with -
     * the flaticon set and the Font Awesome classes side by side, as they already are.
     */
    function menuIconChoices()
    {
        return \Illuminate\Support\Facades\DB::table('permissions')
            ->whereNotNull('icon')
            ->where('icon', '!=', '')
            ->distinct()
            ->orderBy('icon')
            ->pluck('icon');
    }
}
if (!function_exists('sidebarManagerRoles')) {
    /** The roles a super admin can arrange a menu for, in the Sidebar Manager's picker. */
    function sidebarManagerRoles()
    {
        return \Illuminate\Support\Facades\DB::table('roles')->orderBy('id')->get(['id', 'name']);
    }
}

if (!function_exists('sidebarManagerRoleId')) {
    /**
     * Which role the Sidebar Manager is currently arranging.
     *
     * Remembered in the session so it survives the ajax round trips the manager makes while
     * dragging, and falls back to the editor's own role. Only a role that exists is honoured,
     * so a stale or hand-edited session value cannot aim the manager at nothing.
     */
    function sidebarManagerRoleId()
    {
        $selected = session('sidebar_manager_role_id');

        if ($selected && \Illuminate\Support\Facades\DB::table('roles')->where('id', $selected)->exists()) {
            return (int) $selected;
        }

        return auth()->check() ? (int) auth()->user()->role_id : 0;
    }
}

if (!function_exists('dayAbbreviation')) {
    /**
     * The short weekday label a schedule is normally read in - the M of MWF.
     *
     * Keyed on the weekday's own name rather than the weekend table's `order`,
     * because a school can move the start of its week from General Settings (see
     * SmSystemSettingController), which rewrites every `order`: Monday sits at 1
     * here but was seeded as 3. A day renamed past this list keeps its own first
     * three letters instead of being guessed at.
     */
    function dayAbbreviation($name)
    {
        $abbreviations = [
            'monday' => 'M',
            'tuesday' => 'T',
            'wednesday' => 'W',
            'thursday' => 'Th',
            'friday' => 'F',
            'saturday' => 'S',
            'sunday' => 'Su',
        ];

        $key = strtolower(trim((string) $name));

        return $abbreviations[$key] ?? ucfirst(substr($key, 0, 3));
    }
}

if (!function_exists('groupedScheduleSlots')) {
    /**
     * One line per arrangement, instead of one per weekday.
     *
     * A class routine stores a row per day, so a subject met MWF at eight by the
     * same teacher in the same section arrived as three rows. Listing all three
     * repeated the section and the teacher word for word and buried the one thing
     * that differs between blocks, which is exactly what someone reading a
     * curriculum is looking for. This is how the schedule already reads on Enroll
     * in Subjects, where the row is the block and only the days stack inside it.
     *
     * Grouped on section, time and teacher, so two sections of one subject - or
     * the same hour handed to a second teacher - still stand apart. Days are
     * ordered by the weekend table's `order`, which honours a school whose week
     * starts somewhere other than Monday; sorting by day id would read "FM" for a
     * Monday-and-Friday pair, those ids beginning at Saturday.
     */
    function groupedScheduleSlots($slots)
    {
        return collect($slots)
            ->groupBy(function ($slot) {
                return implode('|', [
                    $slot->section_id,
                    $slot->start_time,
                    $slot->end_time,
                    $slot->teacher_id,
                ]);
            })
            ->map(function ($group) {
                $ordered = $group->sortBy(function ($slot) {
                    return optional($slot->weekend)->order ?? PHP_INT_MAX;
                });
                $first = $ordered->first();

                return [
                    'days' => $ordered->map(function ($slot) {
                        return dayAbbreviation(optional($slot->weekend)->name);
                    })->unique()->implode(''),
                    'start_time' => $first->start_time,
                    'end_time' => $first->end_time,
                    'section' => optional($first->section)->section_name,
                    'teacher' => optional($first->teacherDetail)->full_name,
                    /* Earliest hour first, then by the day it starts on, so the cell
                       reads down the week the way a timetable does. Padded because
                       these sort as text: "9" would otherwise land after "10". */
                    'sort_key' => $first->start_time . '|' . str_pad(
                        (string) (optional($first->weekend)->order ?? 99), 2, '0', STR_PAD_LEFT
                    ),
                ];
            })
            ->sortBy('sort_key')
            ->values();
    }
}

if (!function_exists('activeSemesterId')) {
    /**
     * The term the school is running right now.
     *
     * is_active marks it; active_status is a delete flag and every term carries it.
     * Ordered so the answer is stable when more than one is left open by mistake -
     * two is one too many, but a report that changes its mind between page loads is
     * worse than one that picks the earliest and stays there.
     *
     * Falls back to the school on the row rather than the logged-in user, because
     * this also runs from a backfill where nobody is logged in.
     */
    function activeSemesterId($schoolId = null)
    {
        $schoolId = $schoolId ?: (auth()->check() ? auth()->user()->school_id : null);

        return \App\Semester::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');
    }
}

if (!function_exists('roomCapacityShortfall')) {
    /**
     * Whether a room seats fewer people than the block that would sit in it.
     *
     * The roll is the block's - blockStudents() reads the registrations for this
     * exact class, section and subject, which is who actually turns up. The count
     * this replaces asked sm_students for a class and section and then narrowed it
     * by the current academic year against a column holding the year of admission:
     * it returned zero for everyone, always, so the capacity filter built on it had
     * never once hidden a room.
     *
     * Reported rather than enforced, which is why this returns a description and
     * not a boolean. Two classes cannot share a room at one hour - that is a
     * contradiction and is refused - but a room being too small is a fact about the
     * building: refuse the save and a block of 45 in a school whose largest room
     * seats 40 can be timetabled nowhere at all.
     *
     * A room with no capacity recorded is passed over. Nothing is known about it,
     * so nothing is claimed.
     *
     * @return array{room: string, capacity: int, students: int, subject: string}|null
     */
    function roomCapacityShortfall($roomId, $classId, $sectionId, $subjectId)
    {
        if (!$roomId || !$subjectId || !$classId) {
            return null;
        }

        $room = \App\SmClassRoom::where('school_id', auth()->user()->school_id)->find($roomId);

        if (!$room || !$room->capacity) {
            return null;
        }

        $seated = count(blockStudents($classId, $sectionId, $subjectId));

        if ($seated <= $room->capacity) {
            return null;
        }

        return [
            'room' => $room->room_no,
            'capacity' => (int) $room->capacity,
            'students' => $seated,
            'subject' => optional(\App\SmSubject::find($subjectId))->subject_name ?: 'Subject',
        ];
    }
}
