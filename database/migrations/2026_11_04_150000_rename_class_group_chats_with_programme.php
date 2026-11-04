<?php

use App\Course;
use App\Scopes\StatusAcademicSchoolScope;
use App\SmAcademicYear;
use App\SmClass;
use App\SmSection;
use App\SmSubject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Puts the programme at the front of every class group chat's name.
     *
     * A school running more than one programme has a "First Year(A)" in each, and
     * the name was the only thing on screen distinguishing one group from another -
     * so BSIT's first years and BSBA's were, to a reader, the same group twice.
     *
     * Every year's groups, not only the running one: a name is read wherever the
     * group is listed, including in the history the term filter brings back when
     * that term comes round again.
     */
    public function up(): void
    {
        $groups = DB::table('chat_groups')->whereNotNull('subject_id')->get();

        if ($groups->isEmpty()) {
            return;
        }

        /* Looked up once each rather than per group - the four lists together are
           smaller than the number of groups in any school that has been running a
           while. */
        $subjects = SmSubject::withoutGlobalScope(StatusAcademicSchoolScope::class)->get()->keyBy('id');
        $classes = SmClass::withoutGlobalScope(StatusAcademicSchoolScope::class)->get()->keyBy('id');
        $sections = SmSection::withoutGlobalScope(StatusAcademicSchoolScope::class)->get()->keyBy('id');
        $years = SmAcademicYear::get()->keyBy('id');
        $courses = Course::get()->keyBy('id');

        foreach ($groups as $group) {
            $subject = $subjects->get($group->subject_id);
            $course = $subject ? $courses->get($subject->course_id) : null;

            /* The basic-education placeholder is not a programme anyone names, and
               Course::selectable() hides it everywhere else. */
            $code = optional($course)->course_code;
            $programme = ($code && $code !== '__BASIC_ED__') ? $code . ' ' : '';

            $name = $programme
                . optional($classes->get($group->class_id))->class_name
                . '(' . optional($sections->get($group->section_id))->section_name . ') - '
                . optional($subject)->subject_name
                . ' - ' . optional($years->get($group->academic_id))->year;

            if ($group->name !== $name) {
                DB::table('chat_groups')->where('id', $group->id)->update(['name' => $name]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: a name is cosmetic, and the shape it had before carried less
     * than the one it has now.
     */
    public function down(): void
    {
        //
    }
};
