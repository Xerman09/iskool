<?php

namespace App\Listeners;

use App\Events\CreateClassGroupChat;
use App\Models\InvitationType;
use App\Models\StudentRecord;
use App\Scopes\StatusAcademicSchoolScope;
use App\SmAcademicYear;
use App\SmAssignSubject;
use App\SmClass;
use App\SmOptionalSubjectAssign;
use App\SmSection;
use App\SmStaff;
use App\SmSubject;
use App\User;
use Modules\Chat\Entities\Group;
use Modules\Chat\Entities\GroupUser;
use Modules\Chat\Entities\Invitation;

class ListenCreateClassGroupChat
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  CreateClassGroupChat  $event
     * @return void
     */
    public function handle(CreateClassGroupChat $event)
    {
        $group = Group::where([
            'class_id' => $event->assign_subject->class_id,
            'section_id' => $event->assign_subject->section_id,
            'subject_id' => $event->assign_subject->subject_id,
            'school_id' => $event->assign_subject->school_id,
            'academic_id' => $event->assign_subject->academic_id
        ])->first();

        $section = SmSection::find($event->assign_subject->section_id);

        if (!$group){

            $group = Group::create([
               'name' => $this->groupName($event->assign_subject),
               'class_id' => $event->assign_subject->class_id,
                'section_id' => $event->assign_subject->section_id,
                'subject_id' => $event->assign_subject->subject_id,
                /* The term comes off the subject - a block has no semester column of
                   its own - so the chat list can show this term's classes and leave
                   last term's behind. */
                'semester_id' => SmSubject::withOutGlobalScope(StatusAcademicSchoolScope::class)
                    ->where('id', $event->assign_subject->subject_id)
                    ->value('semester_id'),
                'school_id' => $event->assign_subject->school_id,
                'academic_id' => $event->assign_subject->academic_id,
                'created_by' => $event->assign_subject->teacher_id ?? 1,
            ]);
        }

        /* Kept in step on every event, not only at creation, so a subject or section
           renamed afterwards - or a naming rule changed, as the programme prefix was -
           reaches the groups already standing. */
        $currentName = $this->groupName($event->assign_subject);
        if ($group->name !== $currentName) {
            $group->name = $currentName;
            $group->save();
        }

        /* Outside the "group is new" branch on purpose. Students join a block weeks
           after Assign Subject creates it, and adding them only at creation meant a
           group that existed before anyone registered stayed empty for good. Run
           every time, the event re-syncs the members, which also lets a backfill put
           right what is already wrong. createGroupUser() is idempotent and raises the
           invitation as well. */
        foreach ($this->studentsOf($event->assign_subject) as $record){
            $studentUserId = optional($record->studentDetail)->user_id;

            if ($studentUserId) {
                createGroupUser($group, $studentUserId, 2);
            }
        }

        GroupUser::where([
            'group_id' => $group->id,
            'role' => 1
        ])->delete();

        $teachers = SmAssignSubject::where([
            'class_id' => $event->assign_subject->class_id,
            'section_id' => $event->assign_subject->section_id,
            'subject_id' => $event->assign_subject->subject_id,
            'school_id' => $event->assign_subject->school_id,
            'academic_id' => $event->assign_subject->academic_id
        ])->get();


        foreach($teachers as $teacher){
            $teacher = SmStaff::find($teacher->teacher_id);
            if($teacher && $teacher = $teacher->staff_user){
                createGroupUser($group, $teacher->id);
            }

        }

        $admins = User::whereIn('role_id', [5])->where('school_id', $event->assign_subject->school_id)->get();

        foreach($admins as $admin){
            createGroupUser($group, $admin->id);
        }

    }

    /**
     * Who actually sits in this block.
     *
     * Registration records the exact block a student chose, so that is what decides
     * the group. It used to ask for every student whose record carried the block's
     * class and section - but in a college the record's section is advisory and is
     * null for every student here, so the query found nobody and no student was ever
     * put in a group chat.
     *
     * Falls back to class and section where there is no registration to read, which
     * is how basic education works: the grade level sets the load and there are no
     * blocks to choose between.
     *
     * Reads school and year off the block rather than from the session, because this
     * also runs from a backfill, where there is no logged-in user to ask.
     */
    private function studentsOf($assignSubject)
    {
        $studentIds = SmOptionalSubjectAssign::where('assign_subject_id', $assignSubject->id)
            ->where('school_id', $assignSubject->school_id)
            ->where('academic_id', $assignSubject->academic_id)
            ->pluck('student_id')
            ->filter()
            ->unique();

        if ($studentIds->isEmpty()) {
            return StudentRecord::with('studentDetail')
                ->where('class_id', $assignSubject->class_id)
                ->where('section_id', $assignSubject->section_id)
                ->where('school_id', $assignSubject->school_id)
                ->get();
        }

        return StudentRecord::with('studentDetail')
            ->whereIn('student_id', $studentIds)
            ->where('school_id', $assignSubject->school_id)
            ->where('academic_id', $assignSubject->academic_id)
            ->where('is_promote', 0)
            ->get();
    }

    /**
     * What the group is called in a chat list.
     *
     * The programme leads, because without it two groups are indistinguishable: a
     * school running BSIT and BSBA has a "First Year(A)" in each, and the name on
     * screen was the only thing telling them apart - which it did not.
     *
     * Basic education has no programme (its course is the __BASIC_ED__ placeholder,
     * which Course::selectable() hides everywhere else), so there the name starts at
     * the year level as it always did.
     */
    public function groupName($data, $withTeacherId = true){
        $class = SmClass::withOutGlobalScope(StatusAcademicSchoolScope::class)->find($data->class_id);
        $section = SmSection::withOutGlobalScope(StatusAcademicSchoolScope::class)->find($data->section_id);
        $subject = SmSubject::withOutGlobalScope(StatusAcademicSchoolScope::class)->find($data->subject_id);
        $academic_year = SmAcademicYear::find($data->academic_id);

        $courseCode = optional(\App\Course::find(@$subject->course_id))->course_code;
        $programme = ($courseCode && $courseCode !== '__BASIC_ED__') ? $courseCode . ' ' : '';

        return $programme . @$class->class_name . '(' . @$section->section_name . ') - '
            . @$subject->subject_name . ' - ' . @$academic_year->year;
    }
}
