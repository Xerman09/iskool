<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Attendance and pay for a month, in one page - see
     * StaffStandardReportController.
     *
     * Seeded under "staff_report", the sidebar section the staff attendance and
     * payroll reports are already grouped into (see the 7.0.2 migration, which
     * sets their parent_route). The main sidebar is built from this table rather
     * than from any blade, so a row here is all this report needs to appear.
     *
     * The route is staff-standard-report, not staff-report: staff_report is the
     * section itself, and a child of the same name would collide with its parent.
     *
     * Admin side only: this page carries every member of staff's pay, which is
     * not something a teacher may read about their colleagues.
     *
     * Granted to Admin and Accountant - the two roles that run payroll. Super
     * admin needs no row: SidebarDataStore::permissions() hands role 1 every
     * is_admin menu outright, and UserRolePermission lets it past the route check.
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'staff-standard-report')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'staff_report',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Standard Staff Report',
                'route' => 'staff-standard-report',
                'parent_route' => 'staff_report',
                'type' => 2,
                'lang_name' => 'reports.standard_staff_report',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                /* After the attendance and payroll reports it summarises, so it reads
                   as the overview of the section rather than as a rival to them. */
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

        $permissionId = DB::table('permissions')->where('route', 'staff-standard-report')->value('id');
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
        $permissionId = DB::table('permissions')->where('route', 'staff-standard-report')->value('id');

        DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'staff-standard-report')->delete();

        DB::table('sidebars')->truncate();
    }
};
