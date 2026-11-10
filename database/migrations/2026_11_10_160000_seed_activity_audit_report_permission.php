<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Everything that happened to money in a period, in the order it happened -
     * billing raised, receipts taken, expenses and wages paid. See
     * ActivityAuditReportController.
     *
     * Seeded under "accounts_report" rather than beside the fees reports: it
     * crosses all of them, and whoever reads it is reconciling the books rather
     * than chasing a student. Position 23.
     *
     * Granted to Admin and Accountant, the two roles that keep the books. Super
     * admin needs no row: SidebarDataStore::permissions() hands role 1 every
     * is_admin menu outright, and UserRolePermission lets it past the route check.
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'activity-audit-report')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'accounts_report',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Transaction Activity',
                'route' => 'activity-audit-report',
                'parent_route' => 'accounts_report',
                'type' => 2,
                'lang_name' => 'reports.activity_audit_report',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                /* After the reports it summarises, so it reads as the overview of the
                   section rather than as a rival to them. */
                'position' => 23,
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

        $permissionId = DB::table('permissions')->where('route', 'activity-audit-report')->value('id');
        $schoolId = 1;

        foreach ([5, 6] as $roleId) { // Admin, Accountant
            $alreadyGranted = DB::table('assign_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->where('school_id', $schoolId)
                ->exists();

            if (!$alreadyGranted) {
                DB::table('assign_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'status' => 1,
                    'menu_status' => 1,
                    'saas_schools' => null,
                    'created_by' => 1,
                    'updated_by' => 1,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        /* The sidebar is a cached build of the rows above, so it has to be dropped
           for the new item to appear. */
        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('route', 'activity-audit-report')->value('id');

        DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'activity-audit-report')->delete();

        DB::table('sidebars')->truncate();
    }
};
