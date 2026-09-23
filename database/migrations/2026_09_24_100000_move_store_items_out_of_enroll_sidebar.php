<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Item Order Approval, the staff Item Store and My Transactions had been
     * seeded under the staff "Enroll" dropdown, which is about enrollment, not
     * purchases. Move them into their own top-level "Store" section.
     *
     * The new section is granted to every role that already has at least one of
     * the three children - a child whose parent section isn't granted falls back
     * to rendering as a loose top-level item (see
     * 2026_09_03_130000_restructure_student_enroll_fees_sidebar.php).
     */
    private $children = [
        'item-order-approval',
        'staff-item-store',
        'staff-transaction-history',
    ];

    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'store-section')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'store',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Store',
                'route' => 'store-section',
                'parent_route' => null,
                'type' => 1,
                'lang_name' => 'academics.store_section',
                'icon' => 'fas fa-shopping-cart',
                'status' => 1,
                'menu_status' => 1,
                'position' => 7,
                'is_saas' => 0,
                'relate_to_child' => 0,
                'is_menu' => 1,
                'is_admin' => 1,
                'is_teacher' => 1,
                'is_student' => 0,
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

        $sectionId = DB::table('permissions')->where('route', 'store-section')->value('id');
        $childIds = DB::table('permissions')->whereIn('route', $this->children)->pluck('id');

        $grants = DB::table('assign_permissions')
            ->whereIn('permission_id', $childIds)
            ->select('role_id', 'school_id')
            ->distinct()
            ->get();

        foreach ($grants as $grant) {
            $alreadyGranted = DB::table('assign_permissions')
                ->where('role_id', $grant->role_id)
                ->where('permission_id', $sectionId)
                ->where('school_id', $grant->school_id)
                ->exists();

            if (!$alreadyGranted) {
                DB::table('assign_permissions')->insert([
                    'permission_id' => $sectionId,
                    'role_id' => $grant->role_id,
                    'status' => 1,
                    'menu_status' => 1,
                    'saas_schools' => null,
                    'created_by' => 1,
                    'updated_by' => 1,
                    'school_id' => $grant->school_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('permissions')->whereIn('route', $this->children)->update([
            'parent_route' => 'store-section',
            'sidebar_menu' => 'store',
            'updated_at' => now(),
        ]);

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('route', $this->children)->update([
            'parent_route' => 'enroll',
            'sidebar_menu' => 'enroll',
            'updated_at' => now(),
        ]);

        $sectionId = DB::table('permissions')->where('route', 'store-section')->value('id');
        DB::table('assign_permissions')->where('permission_id', $sectionId)->delete();
        DB::table('permissions')->where('route', 'store-section')->delete();

        DB::table('sidebars')->truncate();
    }
};
