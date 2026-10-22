<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Leaves the Sidebar Manager to the super admin alone.
     *
     * Every route in the module is gated on the menumanage.index permission, and a sidebar
     * layout is now shared by a whole role - so whoever holds this can rearrange, rename or
     * hide menus for every account of their role, and a rename reaches the whole school.
     *
     * Role 1 needs no grant: UserRolePermission lets it through without consulting
     * assign_permissions at all, so clearing the grants cannot lock the super admin out.
     *
     * Reversing this restores the grants exactly as they were.
     */
    private function grantRows()
    {
        return DB::table('assign_permissions')
            ->join('permissions', 'permissions.id', '=', 'assign_permissions.permission_id')
            ->where('permissions.route', 'menumanage.index')
            ->where('assign_permissions.role_id', '!=', 1);
    }

    public function up(): void
    {
        $removed = (clone $this->grantRows())
            ->select('assign_permissions.permission_id', 'assign_permissions.role_id', 'assign_permissions.school_id')
            ->get();

        if ($removed->isEmpty()) {
            return;
        }

        // Keep a record so down() can put back exactly these grants and nothing else.
        file_put_contents(
            storage_path('app/sidebar_manager_grants_removed.json'),
            $removed->toJson()
        );

        (clone $this->grantRows())->delete();
    }

    public function down(): void
    {
        $path = storage_path('app/sidebar_manager_grants_removed.json');
        if (!file_exists($path)) {
            return;
        }

        foreach (json_decode(file_get_contents($path), true) ?: [] as $grant) {
            $exists = DB::table('assign_permissions')
                ->where('permission_id', $grant['permission_id'])
                ->where('role_id', $grant['role_id'])
                ->where('school_id', $grant['school_id'])
                ->exists();

            if (!$exists) {
                DB::table('assign_permissions')->insert([
                    'permission_id' => $grant['permission_id'],
                    'role_id' => $grant['role_id'],
                    'status' => 1,
                    'menu_status' => 1,
                    'saas_schools' => null,
                    'created_by' => 1,
                    'updated_by' => 1,
                    'school_id' => $grant['school_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        unlink($path);
    }
};
