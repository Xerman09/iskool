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
        DB::table('permissions')->where('route', 'student-course-curriculum')->update([
            'parent_route' => 'enroll',
            'sidebar_menu' => 'enroll',
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('route', 'student-course-curriculum')->update([
            'parent_route' => null,
            'sidebar_menu' => null,
        ]);
    }
};
