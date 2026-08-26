<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $movedRoutes = [
        'academic-year',
        'semester',
        'program',
        'section',
        'class',
        'curriculum-version',
        'subject',
        'curriculum-builder',
        'class-room',
        'assign-program',
        'assign_subject',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'university_setup')->exists();
        if (!$exists) {
            DB::table('permissions')->insert([
                'module' => null,
                'sidebar_menu' => 'university_setup',
                'section_id' => 1,
                'parent_id' => 0,
                'name' => 'University SetUp',
                'route' => 'university_setup',
                'parent_route' => null,
                'type' => 1,
                'lang_name' => 'academics.university_setup',
                'icon' => 'fas fa-university',
                'status' => 1,
                'menu_status' => 1,
                'position' => 5,
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

        // Move the listed setup screens out of "Academics" into the new "University SetUp" section.
        DB::table('permissions')->whereIn('route', $this->movedRoutes)->update([
            'parent_route' => 'university_setup',
            'sidebar_menu' => 'university_setup',
            'updated_at' => now(),
        ]);

        // Grant Admin (role 5) the new section header. Super Admin (role 1) always bypasses.
        $universitySetupId = DB::table('permissions')->where('route', 'university_setup')->value('id');
        $schoolId = 1;
        $alreadyGranted = DB::table('assign_permissions')
            ->where('role_id', 5)
            ->where('permission_id', $universitySetupId)
            ->where('school_id', $schoolId)
            ->exists();

        if (!$alreadyGranted) {
            DB::table('assign_permissions')->insert([
                'permission_id' => $universitySetupId,
                'role_id' => 5,
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

        // Receptionist (role 7) loses access to these setup screens entirely (University SetUp is admin-only).
        $academicsId = DB::table('permissions')->where('route', 'academics')->value('id');
        $movedIds = DB::table('permissions')->whereIn('route', $this->movedRoutes)->pluck('id');
        DB::table('assign_permissions')
            ->where('role_id', 7)
            ->where(function ($q) use ($academicsId, $movedIds) {
                $q->where('permission_id', $academicsId)
                    ->orWhereIn('permission_id', $movedIds);
            })
            ->delete();

        // The sidebar is cached per-user; force everyone to rebuild it on next load.
        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('route', 'academic-year')->update([
            'parent_route' => 'general_settings',
            'sidebar_menu' => 'system_settings',
        ]);

        DB::table('permissions')->whereIn('route', [
            'semester', 'program', 'section', 'class', 'curriculum-version',
            'subject', 'curriculum-builder', 'class-room', 'assign-program', 'assign_subject',
        ])->update([
            'parent_route' => 'academics',
            'sidebar_menu' => 'academics',
        ]);

        $universitySetupId = DB::table('permissions')->where('route', 'university_setup')->value('id');
        DB::table('assign_permissions')->where('permission_id', $universitySetupId)->delete();
        DB::table('permissions')->where('route', 'university_setup')->delete();

        DB::table('sidebars')->truncate();
    }
};
