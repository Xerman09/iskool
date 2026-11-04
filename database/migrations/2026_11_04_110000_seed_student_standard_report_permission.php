<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The per-term standing of a school year's students - see
     * StudentReportController. Seeded into the same "students_report" sidebar
     * section as the reports it sits beside, after the last of them (17).
     *
     * The route is student-standard-report, not student-report: eskooly already
     * serves that URI from SmStudentReportController, and two GET routes on one
     * path means only the first registered is ever reached.
     *
     * Granted to the two roles that already run enrolment and collection. Super
     * admin needs no row - SidebarDataStore::permissions() hands role 1 every
     * is_admin menu outright, and UserRolePermission lets it past the route check.
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'student-standard-report')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'students_report',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Student Standard Report',
                'route' => 'student-standard-report',
                'parent_route' => 'students_report',
                'type' => 2,
                'lang_name' => 'reports.student_report',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                'position' => 18,
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

        $permissionId = DB::table('permissions')->where('route', 'student-standard-report')->value('id');
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

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('route', 'student-standard-report')->value('id');

        DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'student-standard-report')->delete();

        DB::table('sidebars')->truncate();
    }
};
