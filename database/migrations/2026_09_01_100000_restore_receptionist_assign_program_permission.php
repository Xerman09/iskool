<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 2026_08_27_120000_create_university_setup_permission_section.php moved assign-program
     * into the (then admin-only) University Setup bucket and, as a side effect, deleted
     * Receptionist's (role 7) grant on it along with the other pure-setup screens. But
     * assign-program is an enrollment/operational task, not a setup screen - it was later
     * moved back out to Academics and then to Enroll, and Receptionist had been deliberately
     * granted access to it in 2026_08_25_110100_grant_receptionist_assign_program_permission.php.
     * That grant was never restored, so Receptionist lost the ability to enroll students /
     * generate their enrollment invoice. Restore it here.
     */
    public function up(): void
    {
        $roleId = 7; // Receptionist
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

        // The sidebar is cached per-user; force everyone to rebuild it on next load.
        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleId = 7;
        $permissionId = DB::table('permissions')->where('route', 'assign-program')->value('id');

        DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();

        DB::table('sidebars')->truncate();
    }
};
