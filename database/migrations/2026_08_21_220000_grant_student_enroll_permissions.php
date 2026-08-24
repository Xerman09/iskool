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
        $roleId = 2; // Student
        $schoolId = 1;

        $routes = [
            'enroll',
            'student-course-curriculum',
        ];

        foreach ($routes as $route) {
            $permissionId = DB::table('permissions')->where('route', $route)->value('id');
            if (!$permissionId) {
                continue;
            }

            $exists = DB::table('assign_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->where('school_id', $schoolId)
                ->exists();

            if ($exists) {
                continue;
            }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleId = 2;
        $routes = ['enroll', 'student-course-curriculum'];
        $permissionIds = DB::table('permissions')->whereIn('route', $routes)->pluck('id');

        DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
