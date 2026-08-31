<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every assign-program route (index/store/generate-invoice/balance-summary/
     * status-update/alumni-status-update) shares the single 'assign-program'
     * middleware gate, so one permission grant covers all of them.
     */
    public function up(): void
    {
        $roleId = 5; // Admin
        $schoolId = 1;

        $permissionId = DB::table('permissions')->where('route', 'assign-program')->value('id');
        if (!$permissionId) {
            return;
        }

        $exists = DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->where('school_id', $schoolId)
            ->exists();

        if ($exists) {
            return;
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

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleId = 5;
        $permissionId = DB::table('permissions')->where('route', 'assign-program')->value('id');

        DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();

        DB::table('sidebars')->truncate();
    }
};
