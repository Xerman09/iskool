<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every receipt taken in a period and what it was applied to - see
     * CashReceiptsReportController.
     *
     * Seeded under "fees_report", beside the standard fees report it is the
     * counterpart of: that one is per student and says what is owed, this one is
     * per receipt and says what came in. The main sidebar is built from this
     * table rather than from any blade, so a row here is all it needs to appear.
     *
     * Position 21, after the standard fees report at 20.
     *
     * Granted to Admin and Accountant, the two roles that keep the books. Super
     * admin needs no row: SidebarDataStore::permissions() hands role 1 every
     * is_admin menu outright, and UserRolePermission lets it past the route check.
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'cash-receipts-report')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'fees_report',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Cash Receipts Register',
                'route' => 'cash-receipts-report',
                'parent_route' => 'fees_report',
                'type' => 2,
                'lang_name' => 'reports.standard_receipts_report',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                /* After the reports it summarises, so it reads as the overview of the
                   section rather than as a rival to them. */
                'position' => 21,
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

        $permissionId = DB::table('permissions')->where('route', 'cash-receipts-report')->value('id');
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
        $permissionId = DB::table('permissions')->where('route', 'cash-receipts-report')->value('id');

        DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'cash-receipts-report')->delete();

        DB::table('sidebars')->truncate();
    }
};
