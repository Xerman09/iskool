<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $routes = ['program', 'curriculum-version', 'semester', 'curriculum-builder'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $items = [
            ['name' => 'Program', 'route' => 'program', 'lang_name' => 'academics.program', 'position' => 10],
            ['name' => 'Curriculum Version', 'route' => 'curriculum-version', 'lang_name' => 'academics.curriculum_version', 'position' => 11],
            ['name' => 'Semester', 'route' => 'semester', 'lang_name' => 'academics.semester', 'position' => 12],
            ['name' => 'Curriculum Builder', 'route' => 'curriculum-builder', 'lang_name' => 'academics.curriculum_builder', 'position' => 13],
        ];

        foreach ($items as $item) {
            DB::table('permissions')->updateOrInsert(
                ['route' => $item['route'], 'school_id' => 1],
                [
                    'module' => null,
                    'sidebar_menu' => 'academics',
                    'section_id' => 1,
                    'parent_id' => 0,
                    'name' => $item['name'],
                    'parent_route' => 'academics',
                    'type' => 2,
                    'lang_name' => $item['lang_name'],
                    'icon' => null,
                    'svg' => null,
                    'status' => 1,
                    'menu_status' => 1,
                    'position' => $item['position'],
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
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('route', $this->routes)->delete();
    }
};
