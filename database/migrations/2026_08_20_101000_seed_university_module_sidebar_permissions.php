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
            'old_id' => null,
        ];

        $entries = [
            // Curriculum Builder
            ['name' => 'Curriculum Builder', 'route' => 'curriculum-builder', 'parent_route' => 'academics', 'type' => 2, 'lang_name' => 'academics.curriculum_builder', 'position' => 9, 'is_menu' => 1],
            ['name' => 'Add', 'route' => 'curriculum_builder_store', 'parent_route' => 'curriculum-builder', 'type' => 3, 'lang_name' => null, 'position' => 1, 'is_menu' => 0],
            ['name' => 'Delete', 'route' => 'curriculum_builder_delete', 'parent_route' => 'curriculum-builder', 'type' => 3, 'lang_name' => null, 'position' => 3, 'is_menu' => 0],

            // Program
            ['name' => 'Program', 'route' => 'program', 'parent_route' => 'academics', 'type' => 2, 'lang_name' => 'academics.program', 'position' => 10, 'is_menu' => 1],
            ['name' => 'Add', 'route' => 'program_store', 'parent_route' => 'program', 'type' => 3, 'lang_name' => null, 'position' => 1, 'is_menu' => 0],
            ['name' => 'Edit', 'route' => 'program_edit', 'parent_route' => 'program', 'type' => 3, 'lang_name' => null, 'position' => 2, 'is_menu' => 0],
            ['name' => 'Delete', 'route' => 'program_delete', 'parent_route' => 'program', 'type' => 3, 'lang_name' => null, 'position' => 3, 'is_menu' => 0],

            // Curriculum Version
            ['name' => 'Curriculum Version', 'route' => 'curriculum-version', 'parent_route' => 'academics', 'type' => 2, 'lang_name' => 'academics.curriculum_version', 'position' => 11, 'is_menu' => 1],
            ['name' => 'Add', 'route' => 'curriculum_version_store', 'parent_route' => 'curriculum-version', 'type' => 3, 'lang_name' => null, 'position' => 1, 'is_menu' => 0],
            ['name' => 'Edit', 'route' => 'curriculum_version_edit', 'parent_route' => 'curriculum-version', 'type' => 3, 'lang_name' => null, 'position' => 2, 'is_menu' => 0],
            ['name' => 'Activate', 'route' => 'curriculum_version_activate', 'parent_route' => 'curriculum-version', 'type' => 3, 'lang_name' => null, 'position' => 3, 'is_menu' => 0],
            ['name' => 'Delete', 'route' => 'curriculum_version_delete', 'parent_route' => 'curriculum-version', 'type' => 3, 'lang_name' => null, 'position' => 4, 'is_menu' => 0],

            // Semester
            ['name' => 'Semester', 'route' => 'semester', 'parent_route' => 'academics', 'type' => 2, 'lang_name' => 'academics.semester', 'position' => 12, 'is_menu' => 1],
            ['name' => 'Add', 'route' => 'semester_store', 'parent_route' => 'semester', 'type' => 3, 'lang_name' => null, 'position' => 1, 'is_menu' => 0],
            ['name' => 'Edit', 'route' => 'semester_edit', 'parent_route' => 'semester', 'type' => 3, 'lang_name' => null, 'position' => 2, 'is_menu' => 0],
            ['name' => 'Delete', 'route' => 'semester_delete', 'parent_route' => 'semester', 'type' => 3, 'lang_name' => null, 'position' => 3, 'is_menu' => 0],

            // curriculum_builder_update / curriculum_builder_edit permission guard is reused for
            // modal-based edit on the curriculum builder page (no separate menu link).
            ['name' => 'Edit', 'route' => 'curriculum_builder_edit', 'parent_route' => 'curriculum-builder', 'type' => 3, 'lang_name' => null, 'position' => 2, 'is_menu' => 0],
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
            'curriculum-builder', 'curriculum_builder_store', 'curriculum_builder_edit', 'curriculum_builder_delete',
            'program', 'program_store', 'program_edit', 'program_delete',
            'curriculum-version', 'curriculum_version_store', 'curriculum_version_edit', 'curriculum_version_activate', 'curriculum_version_delete',
            'semester', 'semester_store', 'semester_edit', 'semester_delete',
        ])->delete();
    }
};
