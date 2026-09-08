<?php
use App\SmStudent;
use App\Semester;
use App\SmSubject;
use App\SmOptionalSubjectAssign;
use App\SmAssignSubject;

$activeSemester = Semester::where('is_active', 1)->first();

function equivalentSubjectIds(SmSubject $subject)
{
    if ($subject->subject_classification !== 'minor' || !$subject->source_subject_id) {
        return collect([$subject->id]);
    }
    return SmSubject::where('source_subject_id', $subject->source_subject_id)
        ->where('subject_classification', 'minor')
        ->where('semester_id', $subject->semester_id)
        ->where('units', $subject->units)
        ->pluck('id');
}

function unmetPrerequisites($studentId, SmSubject $subject)
{
    $unmet = [];
    foreach ($subject->prerequisites as $prerequisite) {
        $passed = SmOptionalSubjectAssign::where('student_id', $studentId)
            ->where('subject_id', $prerequisite->prerequisite_subject_id)
            ->where('is_pass', 1)
            ->exists();
        if (!$passed) $unmet[] = $prerequisite->prerequisite_subject_id;
    }
    return $unmet;
}

$student = SmStudent::find(6);
echo "Student: {$student->first_name} {$student->last_name}, class_id={$student->class_id}, course_id={$student->course_id}, curriculum_version_id={$student->curriculum_version_id}\n\n";

$subjects = SmSubject::where('course_id', $student->course_id)
    ->where('curriculum_version_id', $student->curriculum_version_id)
    ->where('semester_id', $activeSemester->id)
    ->where(function ($q) use ($student) {
        $q->where('class_id', $student->class_id)->orWhere('subject_classification', 'minor');
    })
    ->with('prerequisites')
    ->get();

$subjects = $subjects->reject(function ($subject) use ($student) {
    return $subject->subject_classification === 'minor'
        && SmOptionalSubjectAssign::where('student_id', $student->id)
            ->whereIn('subject_id', equivalentSubjectIds($subject))
            ->where('is_pass', 1)
            ->exists();
})->values();

foreach ($subjects as $subject) {
    $equivalentIds = equivalentSubjectIds($subject);
    $chosen = SmOptionalSubjectAssign::where('student_id', $student->id)
        ->whereIn('subject_id', $equivalentIds)
        ->value('assign_subject_id');
    $unmet = unmetPrerequisites($student->id, $subject);
    $blocksCount = SmAssignSubject::whereIn('subject_id', $equivalentIds)->count();
    echo "[{$subject->id}] {$subject->subject_name} ({$subject->subject_classification}, class_id={$subject->class_id}, source={$subject->source_subject_id}) equiv=[" . $equivalentIds->implode(',') . "] unmet=" . count($unmet) . " blocks_available={$blocksCount} chosen=" . var_export($chosen, true) . "\n";
}

echo "\nAll SmOptionalSubjectAssign rows for this student:\n";
$assigns = SmOptionalSubjectAssign::where('student_id', $student->id)->get(['id','subject_id','assign_subject_id','record_id','session_id','academic_id','active_status']);
foreach ($assigns as $a) {
    echo "  assign_id={$a->id} subject_id={$a->subject_id} assign_subject_id={$a->assign_subject_id} record_id={$a->record_id} session_id={$a->session_id} academic_id={$a->academic_id} active_status={$a->active_status}\n";
}
