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
        // Enroll section, in order: Curriculum Layout, Assign Program, Assign Misc Fee, Assign Payment Plan
        DB::table('permissions')->where('route', 'curriculum-layout')->update([
            'position' => 6001,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('route', 'assign-program')->update([
            'parent_route' => 'enroll',
            'sidebar_menu' => 'enroll',
            'position' => 6002,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('route', 'misc-fee-assign')->update([
            'position' => 6003,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->where('route', 'payment-plan-assign')->update([
            'parent_route' => 'enroll',
            'sidebar_menu' => 'enroll',
            'position' => 6004,
            'updated_at' => now(),
        ]);

        // University SetUp: add Payment Plan Type after the existing 9 items
        DB::table('permissions')->where('route', 'payment-plan-type')->update([
            'parent_route' => 'university_setup',
            'sidebar_menu' => 'university_setup',
            'position' => 5010,
            'updated_at' => now(),
        ]);

        // Assign Program moved out of Academics into Enroll -> Admin still needs the grant on Enroll's own header
        $enrollId = DB::table('permissions')->where('route', 'enroll')->value('id');
        $alreadyGranted = DB::table('assign_permissions')
            ->where('role_id', 5)
            ->where('permission_id', $enrollId)
            ->where('school_id', 1)
            ->exists();

        if (!$alreadyGranted) {
            DB::table('assign_permissions')->insert([
                'permission_id' => $enrollId,
                'role_id' => 5,
                'status' => 1,
                'menu_status' => 1,
                'saas_schools' => null,
                'created_by' => 1,
                'updated_by' => 1,
                'school_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('route', ['assign-program', 'payment-plan-assign'])->update([
            'parent_route' => 'academics',
            'sidebar_menu' => 'academics',
        ]);

        DB::table('permissions')->where('route', 'payment-plan-type')->update([
            'parent_route' => 'academics',
            'sidebar_menu' => 'academics',
        ]);

        DB::table('sidebars')->truncate();
    }
};
