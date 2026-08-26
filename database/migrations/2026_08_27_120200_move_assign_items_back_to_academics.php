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
        // University SetUp is strictly: Academic Year, Semester, Program, Section, Class,
        // Curriculum Version, Subjects, Curriculum Builder, Class Room. Nothing else.
        DB::table('permissions')->whereIn('route', ['assign-program', 'assign_subject'])->update([
            'parent_route' => 'academics',
            'sidebar_menu' => 'academics',
            'updated_at' => now(),
        ]);

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('route', ['assign-program', 'assign_subject'])->update([
            'parent_route' => 'university_setup',
            'sidebar_menu' => 'university_setup',
        ]);

        DB::table('sidebars')->truncate();
    }
};
