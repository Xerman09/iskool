<?php
use App\SmStudent;
use App\Semester;
use App\SmSubject;
use App\SmAssignSubject;
use App\SmClassRoutineUpdate;
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

$selectedBlocks = [];
foreach ($subjects as $subject) {
    $equivalentIds = equivalentSubjectIds($subject);
    $block = SmAssignSubject::whereIn('subject_id', $equivalentIds)->first();
    if (!$block) {
        echo "NO BLOCK AVAILABLE for [{$subject->id}] {$subject->subject_name} - would show 'no open blocks', skip\n";
        continue;
    }
    echo "Picking block id={$block->id} for [{$subject->id}] {$subject->subject_name}, class_id={$block->class_id}, section_id={$block->section_id}, teacher_id={$block->teacher_id}, max_slots=" . var_export($block->max_slots, true) . "\n";
    $selectedBlocks[] = $block;
}

echo "\n--- Schedule check ---\n";
$schedule = SmClassRoutineUpdate::whereIn('subject_id', collect($selectedBlocks)->pluck('subject_id'))
    ->where(function ($q) use ($selectedBlocks) {
        foreach ($selectedBlocks as $block) {
            $q->orWhere(function ($q2) use ($block) {
                $q2->where('subject_id', $block->subject_id)
                    ->where('class_id', $block->class_id)
                    ->where('section_id', $block->section_id);
            });
        }
    })
    ->get();

echo "Schedule slots found: " . $schedule->count() . "\n";
foreach ($schedule as $s) {
    echo "  subject_id={$s->subject_id} day={$s->day} start={$s->start_time} end={$s->end_time} class_id={$s->class_id} section_id={$s->section_id}\n";
}

$allSlots = $schedule->groupBy('subject_id')->flatten(1);
$conflict = false;
foreach ($allSlots as $a) {
    foreach ($allSlots as $b) {
        if ($a->id === $b->id || $a->subject_id === $b->subject_id) continue;
        if ((int)$a->day !== (int)$b->day) continue;
        if ($a->start_time < $b->end_time && $b->start_time < $a->end_time) {
            echo "CONFLICT between subject {$a->subject_id} and {$b->subject_id} on day {$a->day}\n";
            $conflict = true;
        }
    }
}
if (!$conflict) echo "No schedule conflicts detected.\n";

echo "\n--- Attempting DB insert (will roll back) ---\n";
DB::beginTransaction();
try {
    $record = StudentRecord::where('school_id', $student->school_id)
        ->where('student_id', $student->id)
        ->where('academic_id', getAcademicId())
        ->where('is_promote', 0)
        ->first();
    echo "Record found: " . ($record ? "id={$record->id}" : "NULL (no active StudentRecord!)") . "\n";

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
    echo "ALL INSERTS SUCCEEDED (would have committed)\n";
} catch (\Throwable $e) {
    echo "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
    echo "Rolled back - no real data changed.\n";
}
