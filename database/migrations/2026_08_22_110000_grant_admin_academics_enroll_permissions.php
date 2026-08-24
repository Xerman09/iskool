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
        $roleId = 5; // Admin
        $schoolId = 1;

        $academicsId = DB::table('permissions')->where('route', 'academics')->value('id');
        $enrollId = DB::table('permissions')->where('route', 'enroll')->value('id');

        $academicsChildren = DB::table('permissions')->where('parent_route', 'academics')->pluck('id', 'route');
        $enrollChildren = DB::table('permissions')->where('parent_route', 'enroll')->pluck('id', 'route');

        $academicsGrandchildren = DB::table('permissions')->whereIn('parent_route', $academicsChildren->keys())->pluck('id');
        $enrollGrandchildren = DB::table('permissions')->whereIn('parent_route', $enrollChildren->keys())->pluck('id');

        $allIds = collect([$academicsId, $enrollId])
            ->merge($academicsChildren->values())
            ->merge($enrollChildren->values())
            ->merge($academicsGrandchildren)
            ->merge($enrollGrandchildren)
            ->unique()
            ->filter();

        foreach ($allIds as $permissionId) {
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
        // Intentionally left as a no-op: reverting would need to distinguish
        // permissions granted by this migration from ones granted before it.
    }
};
