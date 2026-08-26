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
        $exists = DB::table('permissions')->where('route', 'subject-completion')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'academics',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Subject Completion',
                'route' => 'subject-completion',
                'parent_route' => 'academics',
                'type' => 2,
                'lang_name' => 'academics.subject_completion',
                'icon' => '',
                'status' => 1,
                'menu_status' => 1,
                'position' => 20,
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
                'alternate_module' => 'University',
                'user_id' => null,
                'school_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionId = DB::table('permissions')->where('route', 'subject-completion')->value('id');
        $schoolId = 1;

        // Admin and Teacher can mark pass/fail. Super Admin bypasses automatically.
        foreach ([5, 4] as $roleId) {
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
        $permissionId = DB::table('permissions')->where('route', 'subject-completion')->value('id');
        DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'subject-completion')->delete();

        DB::table('sidebars')->truncate();
    }
};
