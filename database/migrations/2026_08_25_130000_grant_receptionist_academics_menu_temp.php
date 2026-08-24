<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Temporary: Receptionist already has leaf access to 'curriculum-layout' and
     * 'assign-program', but not the parent 'academics' sidebar menu, so the section
     * never renders for their role. Grant the parent menu itself for now so the
     * links are reachable. TODO: once enrollment/school-setup screens are split
     * into their own dedicated menu section (separate from Academics), remove this
     * broad grant and give Receptionist access to that section instead.
     */
    public function up(): void
    {
        $roleId = 7; // Receptionist
        $schoolId = 1;

        $permissionId = DB::table('permissions')->where('route', 'academics')->value('id');
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleId = 7;
        $permissionId = DB::table('permissions')->where('route', 'academics')->value('id');

        DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();
    }
};
