<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reception collects payments and can now assign a payment plan directly from
     * an invoice's own page (PaymentPlanAssignController::store()'s invoice_id
     * path) - same permission the dedicated Assign Payment Plan screen already
     * uses, per grant_admin_payment_plan_permissions.php for role_id=5 (Admin).
     */
    public function up(): void
    {
        $roleId = 7; // Receptionist
        $schoolId = 1;

        $permissionId = DB::table('permissions')->where('route', 'payment-plan-assign')->value('id');
        if (!$permissionId) {
            return;
        }

        $exists = DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->where('school_id', $schoolId)
            ->exists();

        if (!$exists) {
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
        $roleId = 7;
        $permissionId = DB::table('permissions')->where('route', 'payment-plan-assign')->value('id');

        DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();
    }
};
