<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Assign Tuition: one amount per year level, for a basic-education school.
     * See ClassTuitionController.
     *
     * Sits in Academics at position 14, immediately before Assign Misc Fee, so
     * the two read in the order a registrar works - the tuition first, then the
     * fees added to it.
     *
     * Shown only on a basic-education deployment. A college prices its tuition
     * per unit on the course, and a second screen for the same number is the
     * fault this one exists to undo - so the menu entry is hidden there, the way
     * Program and Curriculum Layout are hidden on basic education. The
     * controller refuses the route as well, since a hidden menu is not a lock.
     *
     * Granted to Admin alone. Setting what a school charges is not a cashier's
     * job, and the receptionist who collects the money should not be the one who
     * decides it. Super admin needs no row - SidebarDataStore::permissions()
     * hands role 1 every is_admin menu outright.
     */
    public function up(): void
    {
        if (!DB::table('permissions')->where('route', 'class-tuition')->exists()) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'academics',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Assign Tuition',
                'route' => 'class-tuition',
                'parent_route' => 'academics',
                'type' => 2,
                'lang_name' => 'academics.tuition_setup',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                'position' => 14,
                'is_saas' => 0,
                'relate_to_child' => 0,
                /* Hidden outside basic education. SidebarDataStore flips this the
                   same way it flips the four tertiary-only entries, so the value
                   seeded here is only the starting point. */
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

        $permissionId = DB::table('permissions')->where('route', 'class-tuition')->value('id');
        $schoolId = 1;

        foreach ([5] as $roleId) { // Admin
            $granted = DB::table('assign_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->where('school_id', $schoolId)
                ->exists();

            if (!$granted) {
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
        $permissionId = DB::table('permissions')->where('route', 'class-tuition')->value('id');

        DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'class-tuition')->delete();

        DB::table('sidebars')->truncate();
    }
};
