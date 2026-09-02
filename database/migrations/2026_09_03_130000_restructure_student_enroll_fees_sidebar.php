<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The real student sidebar is built entirely from the `permissions` table
     * (parent_route chains) materialized per-user into the `sidebars` table -
     * resources/views/backEnd/partials/student_sidebar.blade.php is dead code,
     * never included by the actual layout (backEnd.partials.sidebar renders
     * <x-sidebar-component /> instead, which reads from `permissions`/`sidebars`).
     *
     * Two fixes:
     * 1. 'enroll' had is_student=0, so it was never synced into a student's own
     *    sidebar tree at all - its children (Curriculum Layout, Enroll in
     *    Subjects, etc.) fell back to rendering as flat top-level items instead
     *    of nesting under an "Enroll" dropdown.
     * 2. Regroup: Enroll keeps only Curriculum Layout + Enroll in Subjects; Item
     *    Store becomes its own top-level entry; a new "Fees" section groups Pay
     *    Fees, Balance Summary, and Transaction History.
     */
    public function up(): void
    {
        DB::table('permissions')->where('route', 'enroll')->update([
            'is_student' => 1,
            'updated_at' => now(),
        ]);

        $exists = DB::table('permissions')->where('route', 'student-fees-section')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'fees',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'Fees',
                'route' => 'student-fees-section',
                'parent_route' => null,
                'type' => 1,
                'lang_name' => 'fees.fees',
                'icon' => 'fas fa-money',
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
        $permissionId = DB::table('permissions')->where('route', 'student-fees-section')->value('id');

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

        // Item Store: promoted out of Enroll into its own top-level entry.
        DB::table('permissions')->where('route', 'student-item-store')->update([
            'parent_route' => null,
            'icon' => 'fas fa-shopping-cart',
            'updated_at' => now(),
        ]);

        // Balance Summary + Transaction History + both possible "pay fees" routes
        // (whichever one is active depends on the school's fees_status setting)
        // now nest under the new Fees section instead of Enroll.
        DB::table('permissions')->whereIn('route', [
            'student-balance-summary',
            'student-transaction-ledger',
            'student_fees',
            'fees.student-fees-list',
        ])->update([
            'parent_route' => 'student-fees-section',
            'updated_at' => now(),
        ]);

        // Every student's materialized sidebar is cached/stored the first time
        // they load a page and never rebuilt automatically after that - force a
        // rebuild so this restructuring (and anything queued behind it) actually
        // shows up, matching the same fix an earlier migration in this project
        // already used for the same reason.
        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('route', 'enroll')->update([
            'is_student' => 0,
            'updated_at' => now(),
        ]);

        DB::table('permissions')->whereIn('route', [
            'student-item-store',
            'student-balance-summary',
            'student-transaction-ledger',
            'student_fees',
            'fees.student-fees-list',
        ])->update([
            'parent_route' => 'enroll',
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('route', 'student-fees-section')->value('id');
        DB::table('assign_permissions')->where('role_id', 2)->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('route', 'student-fees-section')->delete();

        DB::table('sidebars')->truncate();
    }
};
