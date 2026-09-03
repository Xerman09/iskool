<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * role_id 5 ("Admin" - a regular per-school admin, distinct from role_id 1
     * "Super admin") was missing hundreds of permissions, including the entire
     * Fees module (fees.* routes) - it only ever had the legacy fees_collection
     * family plus scattered odds and ends. Per product decision, Admin should
     * have every permission in the system except five whole settings sections:
     * Fees Settings, Exam Settings, Custom Field, General Settings, and Frontend
     * CMS. Those five (and everything nested under them, walked recursively via
     * parent_route) are excluded; everything else not already granted is added.
     */
    private function excludedPermissionIds(): array
    {
        $excludedRoots = ['fees_settings', 'exam_settings', 'custom_field', 'general_settings', 'frontend_cms'];

        $routeClosure = $excludedRoots;
        do {
            $before = count($routeClosure);
            $children = DB::table('permissions')->whereIn('parent_route', $routeClosure)->pluck('route')->toArray();
            $routeClosure = array_values(array_unique(array_merge($routeClosure, $children)));
        } while (count($routeClosure) > $before);

        return DB::table('permissions')->whereIn('route', $routeClosure)->pluck('id')->toArray();
    }

    public function up(): void
    {
        $roleId = 5; // Admin
        $schoolId = 1;

        $excludedIds = $this->excludedPermissionIds();

        $allIds = DB::table('permissions')->pluck('id')->toArray();
        $alreadyGranted = DB::table('assign_permissions')
            ->where('role_id', $roleId)
            ->where('school_id', $schoolId)
            ->pluck('permission_id')
            ->toArray();

        $toGrant = array_values(array_diff($allIds, $excludedIds, $alreadyGranted));

        $now = now();
        $rows = array_map(fn ($permissionId) => [
            'permission_id' => $permissionId,
            'role_id' => $roleId,
            'status' => 1,
            'menu_status' => 1,
            'saas_schools' => null,
            'created_by' => 1,
            'updated_by' => 1,
            'school_id' => $schoolId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $toGrant);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('assign_permissions')->insert($chunk);
        }

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op beyond the sidebar cache: up() only adds grants that
     * weren't already there, and by the time anyone rolls this back there's no
     * reliable way to tell those apart from grants that predate this migration
     * (or were added by something else since) without a marker column. Blindly
     * deleting "every non-excluded grant for role 5" would also wipe whatever
     * legitimately existed before this ran. If Admin's access needs walking
     * back, do it deliberately via Role & Permission management instead.
     */
    public function down(): void
    {
        DB::table('sidebars')->truncate();
    }
};
