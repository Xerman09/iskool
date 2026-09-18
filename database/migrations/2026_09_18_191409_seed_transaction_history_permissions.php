<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Two new read-only history views, both previously missing entirely:
     * - fees.transaction-history: admin/reception side, lists FmFeesTransaction
     *   payments across BOTH students and staff (audience-filtered, same split
     *   as the Item Order Approval queue and the Fees Invoice list). Granted the
     *   same way fees.fees-invoice-list already is - Admin(5) and
     *   Receptionist(7) - since that's who currently works the invoice list.
     * - staff-transaction-history: staff portal side, a staff member's own
     *   payment history for their item-store purchases. Granted to every
     *   staff-type role, same set as staff-item-store itself.
     */
    public function up(): void
    {
        $schoolId = 1;

        $this->seedPermission([
            'module' => 'Fees',
            'sidebar_menu' => 'fees',
            'section_id' => 1,
            'parent_id' => 0,
            'name' => 'Transaction History',
            'route' => 'fees.transaction-history',
            'parent_route' => 'fees',
            'type' => 2,
            'lang_name' => 'fees::feesModule.transaction_history',
            'position' => 4,
            'is_admin' => 1,
        ], [5, 7], $schoolId);

        $this->seedPermission([
            'module' => null,
            'sidebar_menu' => 'enroll',
            'section_id' => 1,
            'parent_id' => 0,
            'name' => 'My Transactions',
            'route' => 'staff-transaction-history',
            'parent_route' => 'enroll',
            'type' => 2,
            'lang_name' => 'academics.my_transactions',
            'position' => 6007,
            'is_admin' => 1,
            'is_teacher' => 1,
        ], [4, 5, 6, 7, 8, 9], $schoolId);

        DB::table('sidebars')->truncate();
    }

    private function seedPermission(array $attributes, array $roleIds, int $schoolId): void
    {
        $exists = DB::table('permissions')->where('route', $attributes['route'])->exists();
        if (!$exists) {
            DB::table('permissions')->insert(array_merge([
                'icon' => null,
                'status' => 1,
                'menu_status' => 1,
                'is_saas' => 0,
                'relate_to_child' => 0,
                'is_menu' => 1,
                'is_teacher' => 0,
                'is_student' => 0,
                'is_parent' => 0,
                'is_alumni' => 0,
                'created_by' => 1,
                'updated_by' => 1,
                'permission_section' => 0,
                'alternate_module' => null,
                'user_id' => null,
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $attributes));
        }

        $permissionId = DB::table('permissions')->where('route', $attributes['route'])->value('id');

        foreach ($roleIds as $roleId) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['fees.transaction-history', 'staff-transaction-history'] as $route) {
            $permissionId = DB::table('permissions')->where('route', $route)->value('id');
            if ($permissionId) {
                DB::table('assign_permissions')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }

        DB::table('sidebars')->truncate();
    }
};
