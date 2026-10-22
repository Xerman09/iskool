<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The Academic Set Up dropdown, in the order the admin should work through it.
     *
     * The sidebar renders lang_name, so each item points at its own numbered phrase instead
     * of the plain key (common.section, common.class, academics.class_routine, ...) that
     * titles pages everywhere else. name is left alone - that is what the Role Permission
     * screens list.
     *
     * route => [numbered lang_name, position, original lang_name, original position]
     */
    private $setupMenu = [
        'academic-year'        => ['academics.setup_step_1',   5001, 'common.academic_year',            5001],
        'semester'             => ['academics.setup_step_2',   5002, 'academics.semester',              5002],
        'program'              => ['academics.setup_step_3',   5003, 'academics.program',               5003],
        'section'              => ['academics.setup_step_4',   5004, 'common.section',                  5004],
        'class'                => ['academics.setup_step_5',   5005, 'common.class',                    5005],
        'curriculum-version'   => ['academics.setup_step_6',   5006, 'academics.curriculum_version',    5006],
        'subject'              => ['academics.setup_step_7',   5007, 'common.subjects',                 5007],
        'curriculum-builder'   => ['academics.setup_step_8',   5008, 'academics.curriculum_builder',    5008],
        'class-room'           => ['academics.setup_step_9',   5009, 'academics.class_room',            5009],
        'assign-class-teacher' => ['academics.setup_step_10a', 5010, 'academics.assign_class_teacher',     5],
        'assign_subject'       => ['academics.setup_step_10b', 5011, 'academics.assign_subject',        5011],
        'class_routine'        => ['academics.setup_step_11',  5012, 'academics.class_routine',            8],
        'payment-plan-type'    => ['academics.setup_step_12',  5013, 'academics.payment_plan_type',     5010],
        'down-payment'         => ['academics.setup_step_13',  5014, 'academics.down_payment',          5011],
    ];

    /**
     * Setting up a teacher and a schedule is part of the same walk-through, so these three
     * move in from the Academics dropdown. The reports they feed (Teacher Class Schedule,
     * Subject Completion) stay behind under Academics.
     */
    private $movedIn = ['assign-class-teacher', 'assign_subject', 'class_routine'];

    /** Menu entries that said "Routine" for class scheduling. Exam routines keep their name. */
    private $scheduleNames = [
        'class_routine'                => ['Class Schedule', 'Class Routine'],
        'student_class_routine'        => ['Class Schedule', 'Class Routine'],
        'parent_class_routine'         => ['Class Schedule', 'Class Routine'],
        'class_routine_report'         => ['Class Schedule', 'Class Routine'],
        'teacher_class_routine_report' => ['Teacher Class Schedule', 'Teacher Class Routine'],
        'class-exam-routine-page'      => ['Class Schedule/Exam Routine', 'Class/Exam Routine'],
        // stock eskooly mislabelled this frontend CMS page "Front Result"
        'front-class-routine'          => ['Front Class Schedule', 'Front Result'],
    ];

    public function up(): void
    {
        $this->apply('Academic Set Up', 'academics.academic_setup', 'university_setup', 0, 1, 0);
    }

    /**
     * Only the permissions rows revert here. The phrases themselves live in
     * resources/lang/en/{academics,reports,exam,front_settings,edulia}.php - revert those
     * through git, or the labels keep reading "Class Schedule" after a rollback.
     */
    public function down(): void
    {
        $this->apply('University SetUp', 'academics.university_setup', 'academics', 2, 3, 1);
    }

    private function apply($sectionName, $sectionLangName, $movedParent, $langIndex, $positionIndex, $nameIndex)
    {
        DB::table('permissions')->where('route', 'university_setup')->update([
            'name' => $sectionName,
            'lang_name' => $sectionLangName,
            'updated_at' => now(),
        ]);

        foreach ($this->setupMenu as $route => $menu) {
            $update = [
                'lang_name' => $menu[$langIndex],
                'position' => $menu[$positionIndex],
                'updated_at' => now(),
            ];

            if (in_array($route, $this->movedIn, true)) {
                $update['parent_route'] = $movedParent;
                $update['sidebar_menu'] = $movedParent;
            }

            DB::table('permissions')->where('route', $route)->update($update);
        }

        foreach ($this->scheduleNames as $route => $names) {
            DB::table('permissions')->where('route', $route)->update([
                'name' => $names[$nameIndex],
                'updated_at' => now(),
            ]);
        }

        // The tree is rebuilt from permissions once this is empty.
        DB::table('sidebars')->truncate();

        // sidebar_menus() caches the permission rows per user forever, and only forgets the
        // key when it finds a permission it has not seen - which a rename never is.
        foreach (DB::table('users')->pluck('id') as $userId) {
            Cache::forget('sidebars' . $userId);
        }
    }
};
