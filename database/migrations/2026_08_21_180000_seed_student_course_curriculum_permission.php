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
        $exists = DB::table('permissions')->where('route', 'student-course-curriculum')->exists();
        if ($exists) {
            return;
        }

        DB::table('permissions')->insert([
            'module' => null,
            'sidebar_menu' => null,
            'section_id' => 1,
            'parent_id' => 0,
            'name' => 'Course Curriculum',
            'route' => 'student-course-curriculum',
            'parent_route' => null,
            'type' => 1,
            'lang_name' => 'academics.curriculum_layout',
            'icon' => null,
            'status' => 1,
            'menu_status' => 1,
            'position' => 18,
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('route', 'student-course-curriculum')->delete();
    }
};
