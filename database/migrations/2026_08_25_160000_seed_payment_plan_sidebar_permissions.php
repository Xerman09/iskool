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
        $common = [
            'module' => null,
            'sidebar_menu' => 'academics',
            'section_id' => 1,
            'parent_id' => 0,
            'status' => 1,
            'menu_status' => 1,
            'is_saas' => 0,
            'relate_to_child' => 0,
            'is_admin' => 1,
            'is_teacher' => 0,
            'is_student' => 0,
            'is_parent' => 0,
            'is_alumni' => 0,
            'created_by' => 1,
            'updated_by' => 1,
            'permission_section' => 0,
            'alternate_module' => null,
            'user_id' => null,
            'school_id' => 1,
        ];

        $entries = [
            ['name' => 'Payment Plan Type', 'route' => 'payment-plan-type', 'parent_route' => 'academics', 'type' => 2, 'lang_name' => 'academics.payment_plan_type', 'position' => 16, 'is_menu' => 1],
            ['name' => 'Add', 'route' => 'payment-plan-type-store', 'parent_route' => 'payment-plan-type', 'type' => 3, 'lang_name' => null, 'position' => 1, 'is_menu' => 0],
            ['name' => 'Edit', 'route' => 'payment-plan-type-edit', 'parent_route' => 'payment-plan-type', 'type' => 3, 'lang_name' => null, 'position' => 2, 'is_menu' => 0],
            ['name' => 'Delete', 'route' => 'payment-plan-type-delete', 'parent_route' => 'payment-plan-type', 'type' => 3, 'lang_name' => null, 'position' => 3, 'is_menu' => 0],

            ['name' => 'Assign Payment Plan', 'route' => 'payment-plan-assign', 'parent_route' => 'academics', 'type' => 2, 'lang_name' => 'academics.payment_plan_assign', 'position' => 17, 'is_menu' => 1],
        ];

        $now = now();
        foreach ($entries as $entry) {
            $exists = DB::table('permissions')->where('route', $entry['route'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('permissions')->insert(array_merge($common, $entry, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('route', [
            'payment-plan-type', 'payment-plan-type-store', 'payment-plan-type-edit', 'payment-plan-type-delete',
            'payment-plan-assign',
        ])->delete();
    }
};
