<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 2026_08_27_100000_rename_student_my_schedule_route_to_class_routine.php
     * renamed the "My Schedule" permission's route column to match the existing
     * "student_class_routine" permission, but never removed the now-duplicate
     * row - two permissions sharing one route (id 1608 "Class Routine",
     * parent_route null; id 1777 "My Schedule", parent_route 'enroll') meant the
     * student sidebar synced BOTH, showing the same screen twice.
     */
    public function up(): void
    {
        $duplicate = DB::table('permissions')
            ->where('route', 'student_class_routine')
            ->where('name', 'My Schedule')
            ->where('parent_route', 'enroll')
            ->first();

        if (!$duplicate) {
            return;
        }

        DB::table('assign_permissions')->where('permission_id', $duplicate->id)->delete();
        DB::table('permissions')->where('id', $duplicate->id)->delete();

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The duplicate row is gone for good - re-seeding it would just
        // recreate the original bug. Nothing to reverse.
    }
};
