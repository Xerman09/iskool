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
        $exists = DB::table('permissions')->where('route', 'student-transaction-ledger')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'enroll',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Transaction History',
                'route' => 'student-transaction-ledger',
                'parent_route' => 'enroll',
                'type' => 2,
                'lang_name' => 'academics.transaction_ledger',
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                'position' => 20,
                'is_saas' => 0,
                'relate_to_child' => 0,
                'is_menu' => 1,
                'is_admin' => 0,
                'is_teacher' => 0,
                'is_student' => 1,
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

        $roleId = 2; // Student
        $schoolId = 1;
        $permissionId = DB::table('permissions')->where('route', 'student-transaction-ledger')->value('id');

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

        // The admin-side "Ledger" action lives on AssignProgramController and reuses
        // the existing 'assign-program' permission - same pattern as its balanceSummary()
        // sibling action - so no separate permission row is needed for it.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $studentPermissionId = DB::table('permissions')->where('route', 'student-transaction-ledger')->value('id');
        DB::table('assign_permissions')->where('role_id', 2)->where('permission_id', $studentPermissionId)->delete();
        DB::table('permissions')->where('route', 'student-transaction-ledger')->delete();
    }
};
