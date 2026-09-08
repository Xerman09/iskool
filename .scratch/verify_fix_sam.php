<?php
use App\SmStudent;
use App\Semester;
use App\SmSubject;
use App\SmOptionalSubjectAssign;

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

$rows = $subjects->map(function ($subject) use ($student) {
    $chosen = SmOptionalSubjectAssign::where('student_id', $student->id)
        ->whereIn('subject_id', equivalentSubjectIds($subject))
        ->value('assign_subject_id');
    return [
        'class_id' => $subject->class_id,
        'unmet' => count(unmetPrerequisites($student->id, $subject)),
        'chosen' => $chosen,
    ];
});

// NEW logic
$requiredThisTerm = $rows->filter(fn($r) => $r['class_id'] == $student->class_id);
$eligible = $requiredThisTerm->filter(fn($r) => $r['unmet'] === 0);
$hasSubmitted = $eligible->count() > 0 && $eligible->every(fn($r) => $r['chosen'] !== null);

echo "Student class_id: {$student->class_id}\n";
echo "Total subjects (incl. other-year minors): " . $rows->count() . "\n";
echo "Required this term (own class_id): " . $requiredThisTerm->count() . "\n";
echo "Eligible (own class_id, no unmet prereq): " . $eligible->count() . "\n";
echo "hasSubmitted (with fix): " . ($hasSubmitted ? 'TRUE' : 'FALSE') . "\n";
