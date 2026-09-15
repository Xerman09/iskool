<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The employee counterpart to student-item-store (see
     * 2026_09_02_100300_seed_student_item_store_permission.php) - lives in the
     * shared admin backend rather than a portal of its own, since staff already
     * work from there. Granted by default to every staff-type role: Teacher(4),
     * Admin(5), Accountant(6), Receptionist(7), Librarian(8), Driver(9) - not
     * Super admin(1, a platform account rather than an employee), Student(2) or
     * Parent(3, ordering is explicitly off-limits there), or Customer(10).
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'staff-item-store')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'enroll',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Item Store',
                'route' => 'staff-item-store',
                'parent_route' => 'enroll',
                'type' => 2,
                'lang_name' => 'academics.item_store',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                'position' => 6006,
                'is_saas' => 0,
                'relate_to_child' => 0,
                'is_menu' => 1,
                'is_admin' => 1,
                'is_teacher' => 1,
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

        $permissionId = DB::table('permissions')->where('route', 'staff-item-store')->value('id');
        $schoolId = 1;
        $staffRoleIds = [4, 5, 6, 7, 8, 9]; // Teacher, Admin, Accountant, Receptionist, Librarian, Driver

        foreach ($staffRoleIds as $roleId) {
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
        $permissionId = DB::table('permissions')->where('route', 'staff-item-store')->value('id');

        DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'staff-item-store')->delete();

        DB::table('sidebars')->truncate();
    }
};
