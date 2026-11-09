<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The money counterpart of the standard student report - what was billed,
     * collected, is still owed, and how long it has been owed. See
     * FeesStandardReportController.
     *
     * Seeded under "fees_report", the same sidebar section the module's five
     * reports and the Wallet module's own report are grouped into (see the 7.0.2
     * migration, which sets their parent_route). The main sidebar is built from
     * this table rather than from the module blades, which is how wallet-report
     * reaches the Fees Report menu from another module - so a row here is all this
     * report needs to appear, with no blade edited.
     *
     * Admin side only, as asked: a student already reads their own account off
     * student-transaction-ledger, so is_student stays 0.
     *
     * Granted to the two roles that already run enrolment and collection. Super
     * admin needs no row - SidebarDataStore::permissions() hands role 1 every
     * is_admin menu outright, and UserRolePermission lets it past the route check.
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'fees-standard-report')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'fees_report',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Standard Fees Report',
                'route' => 'fees-standard-report',
                'parent_route' => 'fees_report',
                'type' => 2,
                'lang_name' => 'reports.standard_fees_report',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                /* After the five module reports and the wallet one, so it reads as the
                   summary of the section rather than as one more column of it. */
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

        $permissionId = DB::table('permissions')->where('route', 'fees-standard-report')->value('id');
        $schoolId = 1;

        foreach ([5, 7] as $roleId) { // Admin, Receptionist
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
           for the new item to appear - the same step the student report's own
           migration takes. */
        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('route', 'fees-standard-report')->value('id');

        DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'fees-standard-report')->delete();

        DB::table('sidebars')->truncate();
    }
};
