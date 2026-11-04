<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Names the report "Standard Student Report", and points it at a translation
     * key of its own.
     *
     * It was seeded with lang_name = reports.student_report, which is eskooly's
     * older student report's key - and menuLabel() prefers lang_name over name, so
     * the sidebar showed two entries called "Student Report", one above the other,
     * in the same section. The name column was never what anyone was reading.
     *
     * Kept as its own migration rather than by editing the one that seeded the
     * permission: that one has already run here, so changing it would correct
     * nothing that exists and only this would.
     */
    public function up(): void
    {
        DB::table('permissions')
            ->where('route', 'student-standard-report')
            ->update([
                'name' => 'Standard Student Report',
                'lang_name' => 'reports.standard_student_report',
                'updated_at' => now(),
            ]);

        // The label is read through the permission, so the cached tree has to go.
        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')
            ->where('route', 'student-standard-report')
            ->update([
                'name' => 'Student Standard Report',
                'lang_name' => 'reports.student_report',
                'updated_at' => now(),
            ]);

        DB::table('sidebars')->truncate();
    }
};
