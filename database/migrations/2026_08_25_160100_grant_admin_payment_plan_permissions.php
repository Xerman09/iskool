<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Super admin (role_id=1) bypasses permission checks entirely, but a plain
     * "Admin" role (role_id=5) still needs an explicit assign_permissions grant
     * before the sidebar menu/route actually becomes reachable — same gap we hit
     * with Receptionist earlier for the Academics menu.
     */
    public function up(): void
    {
        $roleId = 5; // Admin
        $schoolId = 1;

        $routes = [
            'payment-plan-type',
            'payment-plan-type-store',
            'payment-plan-type-edit',
            'payment-plan-type-delete',
            'payment-plan-assign',
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
        $roleId = 5;
        $routes = [
            'payment-plan-type', 'payment-plan-type-store', 'payment-plan-type-edit', 'payment-plan-type-delete',
            'payment-plan-assign',
        ];
        $permissionIds = DB::table('permissions')->whereIn('route', $routes)->pluck('id');

        DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
