<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reception's queue of students waiting to be enrolled - see
     * PendingEnrolleesController. Seeded into the same "enroll" sidebar section as
     * assign-program/payment-plan-assign/admin-subject-registration, after that
     * last one's position (6005), and granted to the same two roles that hold
     * assign-program: the queue exists to save them a trip through it.
     *
     * Super admin needs no row in assign_permissions - SidebarDataStore::permissions()
     * gives role 1 every is_admin menu outright, and UserRolePermission lets role 1
     * past the route check - so is_admin/is_menu above are what put this in front of
     * them.
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'pending-enrollees')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'enroll',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Pending Enrollees',
                'route' => 'pending-enrollees',
                'parent_route' => 'enroll',
                'type' => 2,
                'lang_name' => 'academics.pending_enrollees',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                'position' => 6006,
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

        $permissionId = DB::table('permissions')->where('route', 'pending-enrollees')->value('id');
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
        $permissionId = DB::table('permissions')->where('route', 'pending-enrollees')->value('id');

        DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'pending-enrollees')->delete();

        DB::table('sidebars')->truncate();
    }
};
