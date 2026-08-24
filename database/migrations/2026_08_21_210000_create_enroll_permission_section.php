<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'enroll')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'enroll',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Enroll',
                'route' => 'enroll',
                'parent_route' => null,
                'type' => 1,
                'lang_name' => 'academics.enroll_section',
                'icon' => 'fas fa-user-graduate',
                'status' => 1,
                'menu_status' => 1,
                'position' => 6,
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

        // Move our own pages under the new "Enroll" section (does not touch shared Fee Invoice routes)
        DB::table('permissions')->where('route', 'curriculum-layout')->update([
            'parent_route' => 'enroll',
            'sidebar_menu' => 'enroll',
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('route', 'misc-fee-assign')->update([
            'parent_route' => 'enroll',
            'sidebar_menu' => 'enroll',
            'updated_at' => now(),
        ]);

        // Grant the Receptionist role the new "Enroll" section header (children were already granted individually)
        $roleId = 7;
        $schoolId = 1;
        $enrollId = DB::table('permissions')->where('route', 'enroll')->value('id');
        $alreadyGranted = DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $enrollId)
            ->where('school_id', $schoolId)
            ->exists();

        if (!$alreadyGranted) {
            DB::table('assign_permissions')->insert([
                'permission_id' => $enrollId,
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('route', 'curriculum-layout')->update([
            'parent_route' => 'academics',
            'sidebar_menu' => 'academics',
        ]);

        DB::table('permissions')->where('route', 'misc-fee-assign')->update([
            'parent_route' => 'fees',
            'sidebar_menu' => 'fees',
        ]);

        $enrollId = DB::table('permissions')->where('route', 'enroll')->value('id');
        DB::table('assign_permissions')->where('role_id', 7)->where('permission_id', $enrollId)->delete();
        DB::table('permissions')->where('route', 'enroll')->delete();
    }
};
