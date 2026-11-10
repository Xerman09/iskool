<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One sidebar entry for the four receivables views, and the four taken out of
     * the sidebar behind it.
     *
     * They are the same money at four grains - per student, per invoice, per
     * receipt, per event - and they reconcile with each other. Four entries said
     * they were four subjects, and Fees Report had grown to thirteen.
     *
     * The four keep menu_status 1 while losing is_menu, which is the whole trick:
     * the sidebar is built where is_menu is 1 (SidebarDataStore), while Role &
     * Permission lists on menu_status (RolePermissionController and the subModule
     * relation). So they vanish from the menu and stay grantable one by one, which
     * is what makes the tab strip's per-tab checks worth anything.
     *
     * Granted to every role that already held any of the four, so nobody who could
     * reach a view this morning finds no way in this afternoon.
     */
    private const TABS = [
        'fees-standard-report',
        'open-invoices-report',
        'cash-receipts-report',
        'activity-audit-report',
    ];

    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'receivables')->exists();

        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'fees_report',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Receivables',
                'route' => 'receivables',
                'parent_route' => 'fees_report',
                'type' => 2,
                'lang_name' => 'reports.receivables',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                /* Where the standard fees report used to sit, so the section keeps
                   its order. */
                'position' => 20,
                'is_saas' => 0,
                'relate_to_child' => 0,
                'is_menu' => 1,
                'is_admin' => 1,
                'is_teacher' => 0,
                'is_student' => 0,
                'is_parent' => 0,
                'is_alumni' => 0,
                'created_by' => 1,
                'updated_by' => 1,
                'permission_section' => 0,
                'alternate_module' => null,
                'user_id' => null,
                'school_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sectionId = DB::table('permissions')->where('route', 'receivables')->value('id');
        $tabIds = DB::table('permissions')->whereIn('route', self::TABS)->pluck('id');

        /* Out of the sidebar, still in Role & Permission.

           Reparented to fees_report at the same time, which only moves Transaction
           Activity: Role & Permission groups by parent_route, and a tab listed
           under Accounts Report while it is reached from Receivables is a tab
           somebody will look for in the wrong place. */
        DB::table('permissions')->whereIn('route', self::TABS)->update([
            'is_menu' => 0,
            'parent_route' => 'fees_report',
            'sidebar_menu' => 'fees_report',
        ]);

        /* Whoever could open any of the four can open the section that now leads to
           them. Read off the existing grants rather than listed by hand, so a role
           granted a view by someone else since is carried too. */
        $roleIds = DB::table('assign_permissions')
            ->whereIn('permission_id', $tabIds)
            ->distinct()
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            $already = DB::table('assign_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $sectionId)
                ->exists();

            if (!$already) {
                DB::table('assign_permissions')->insert([
                    'permission_id' => $sectionId,
                    'role_id' => $roleId,
                    'status' => 1,
                    'menu_status' => 1,
                    'saas_schools' => null,
                    'created_by' => 1,
                    'updated_by' => 1,
                    'school_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $sectionId = DB::table('permissions')->where('route', 'receivables')->value('id');

        DB::table('assign_permissions')->where('permission_id', $sectionId)->delete();
        DB::table('permissions')->where('route', 'receivables')->delete();

        /* Back into the sidebar on their own, which is where they were - and the
           activity timeline back under Accounts Report, where it was seeded. */
        DB::table('permissions')->whereIn('route', self::TABS)->update(['is_menu' => 1]);

        DB::table('permissions')->where('route', 'activity-audit-report')->update([
            'parent_route' => 'accounts_report',
            'sidebar_menu' => 'accounts_report',
        ]);

        DB::table('sidebars')->truncate();
    }
};
