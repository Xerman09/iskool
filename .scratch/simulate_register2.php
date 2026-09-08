<?php
use App\SmStudent;
use App\Semester;
use App\SmSubject;
use App\SmAssignSubject;
use App\SmOptionalSubjectAssign;
use App\Models\StudentRecord;
use Illuminate\Support\Facades\DB;

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

$student = SmStudent::find(6);
$activeSemester = Semester::where('is_active', 1)->first();

$subjects = SmSubject::where('course_id', $student->course_id)
    ->where('curriculum_version_id', $student->curriculum_version_id)
    ->where('semester_id', $activeSemester->id)
    ->where(function ($q) use ($student) {
        $q->where('class_id', $student->class_id)->orWhere('subject_classification', 'minor');
    })
    ->get()
    ->reject(function ($subject) use ($student) {
        return $subject->subject_classification === 'minor'
            && SmOptionalSubjectAssign::where('student_id', $student->id)
                ->whereIn('subject_id', equivalentSubjectIds($subject))
                ->where('is_pass', 1)
                ->exists();
    })->values();

// Simulate: student ONLY picks blocks for MAJORS, leaves all minors unselected (skipped).
$selectedBlocks = [];
foreach ($subjects as $subject) {
    if ($subject->subject_classification === 'minor') {
        echo "SKIPPING (optional, no choice made) [{$subject->id}] {$subject->subject_name}\n";
        continue; // new behavior: no error, just skip
    }
    $equivalentIds = equivalentSubjectIds($subject);
    $block = SmAssignSubject::whereIn('subject_id', $equivalentIds)->first();
    echo "Picking block id={$block->id} for MAJOR [{$subject->id}] {$subject->subject_name}\n";
    $selectedBlocks[] = $block;
}

echo "\n--- Attempting DB insert (will roll back) ---\n";
DB::beginTransaction();
try {
    $record = StudentRecord::where('school_id', $student->school_id)
        ->where('student_id', $student->id)
        ->where('academic_id', getAcademicId())
        ->where('is_promote', 0)
        ->first();
    echo "Record found: " . ($record ? "id={$record->id}" : "NULL") . "\n";

    foreach ($selectedBlocks as $block) {
        $choice = new SmOptionalSubjectAssign();
        $choice->student_id = $student->id;
        $choice->record_id = optional($record)->id;
        $choice->subject_id = $block->subject_id;
        $choice->assign_subject_id = $block->id;
        $choice->school_id = $student->school_id;
        $choice->session_id = getAcademicId();
        $choice->academic_id = getAcademicId();
        $choice->active_status = 1;
        $choice->save();
        echo "Saved OK: subject_id={$block->subject_id}\n";
    }
    echo "SUCCESS - registration with minors skipped would go through.\n";
} catch (\Throwable $e) {
    echo "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
    echo "Rolled back - no real data changed.\n";
}
