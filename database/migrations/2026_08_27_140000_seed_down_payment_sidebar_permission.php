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
        $exists = DB::table('permissions')->where('route', 'down-payment')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'university_setup',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Down Payment',
                'route' => 'down-payment',
                'parent_route' => 'university_setup',
                'type' => 2,
                'lang_name' => 'academics.down_payment',
                'icon' => '',
                'status' => 1,
                'menu_status' => 1,
                'position' => 5011,
                'is_saas' => 0,
                'relate_to_child' => 0,
                'is_menu' => 1,
                'is_admin' => 1,
                'is_teacher' => 0,
                'is_student' => 0,
                'is_parent' => 0,
                'is_alumni' => 0,
                'created_by' => 1,
                'updated_by' => 1,
                'permission_section' => 0,
                'alternate_module' => 'University',
                'user_id' => null,
                'school_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $downPaymentId = DB::table('permissions')->where('route', 'down-payment')->value('id');
        $alreadyGranted = DB::table('assign_permissions')
            ->where('role_id', 5)
            ->where('permission_id', $downPaymentId)
            ->where('school_id', 1)
            ->exists();

        if (!$alreadyGranted) {
            DB::table('assign_permissions')->insert([
                'permission_id' => $downPaymentId,
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
        $downPaymentId = DB::table('permissions')->where('route', 'down-payment')->value('id');
        DB::table('assign_permissions')->where('permission_id', $downPaymentId)->delete();
        DB::table('permissions')->where('route', 'down-payment')->delete();

        DB::table('sidebars')->truncate();
    }
};
