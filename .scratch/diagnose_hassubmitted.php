<?php
use App\SmStudent;
use App\Semester;
use App\SmSubject;
use App\SmOptionalSubjectAssign;

$activeSemester = Semester::where('is_active', 1)->first();
if (!$activeSemester) {
    echo "No active semester found.\n";
    return;
}
echo "Active semester: {$activeSemester->id} ({$activeSemester->semester_name})\n\n";

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
        if (!$passed) {
            $unmet[] = $prerequisite->prerequisite_subject_id;
        }
    }
    return $unmet;
}

$students = SmStudent::whereNotNull('course_id')->whereNotNull('curriculum_version_id')->whereNotNull('class_id')->get();
echo "Students with course+curriculum+class assigned: " . $students->count() . "\n\n";

foreach ($students as $student) {
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

    if ($subjects->isEmpty()) continue;

    $anyAssignRows = SmOptionalSubjectAssign::where('student_id', $student->id)->exists();
    if (!$anyAssignRows) continue; // never registered at all, not the case we're chasing

    $rows = [];
    foreach ($subjects as $subject) {
        $equivalentIds = equivalentSubjectIds($subject);
        $chosen = SmOptionalSubjectAssign::where('student_id', $student->id)
            ->whereIn('subject_id', $equivalentIds)
            ->value('assign_subject_id');
        $unmet = unmetPrerequisites($student->id, $subject);
        $rows[] = [
            'subject_id' => $subject->id,
            'name' => $subject->subject_name,
            'classification' => $subject->subject_classification,
            'class_id' => $subject->class_id,
            'source_subject_id' => $subject->source_subject_id,
            'equivalent_ids' => $equivalentIds->toArray(),
            'unmet_count' => count($unmet),
            'chosen' => $chosen,
        ];
    }

    $eligible = array_filter($rows, fn($r) => $r['unmet_count'] === 0);
    $hasSubmitted = count($eligible) > 0 && collect($eligible)->every(fn($r) => $r['chosen'] !== null);

    $missing = collect($eligible)->filter(fn($r) => $r['chosen'] === null);

    if (!$hasSubmitted) {
        echo "=== Student #{$student->id} ({$student->first_name} {$student->last_name}, admission {$student->admission_no}) class_id={$student->class_id} ===\n";
        echo "hasSubmitted computes to: FALSE\n";
        echo "Eligible subjects: " . count($eligible) . ", missing choice on: " . $missing->count() . "\n";
        foreach ($rows as $r) {
            echo "  - [{$r['subject_id']}] {$r['name']} ({$r['classification']}, class_id={$r['class_id']}, source={$r['source_subject_id']}) equiv=[" . implode(',', $r['equivalent_ids']) . "] unmet={$r['unmet_count']} chosen=" . var_export($r['chosen'], true) . "\n";
        }
        echo "\n";
    }
}
