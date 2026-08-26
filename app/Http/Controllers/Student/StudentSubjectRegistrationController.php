<?php

namespace App\Http\Controllers\Student;

use App\Semester;
use App\SmSubject;
use App\SmAssignSubject;
use App\SmClassRoutineUpdate;
use App\SmOptionalSubjectAssign;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentBalanceBreakdown;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentSubjectRegistrationController extends Controller
{
    use EnrollmentBalanceBreakdown;

    public function index(Request $request)
    {
        try {
            $student = Auth::user()->student;
            $schoolId = Auth::user()->school_id;

            $activeSemester = null;
            $subjects = collect();

            if ($student && $student->course_id && $student->curriculum_version_id) {
                $activeSemester = Semester::where('school_id', $schoolId)->where('is_active', 1)->first();

                if ($activeSemester && $student->class_id) {
                    $subjects = SmSubject::where('course_id', $student->course_id)
                        ->where('curriculum_version_id', $student->curriculum_version_id)
                        ->where('class_id', $student->class_id)
                        ->where('semester_id', $activeSemester->id)
                        ->get();

                    $existingChoices = SmOptionalSubjectAssign::where('student_id', $student->id)
                        ->whereIn('subject_id', $subjects->pluck('id'))
                        ->pluck('assign_subject_id', 'subject_id');

                    $subjects->each(function ($subject) use ($student, $existingChoices) {
                        $blocks = SmAssignSubject::where('subject_id', $subject->id)
                            ->with('teacher', 'section')
                            ->get();

                        $blocks->each(function ($block) {
                            $block->scheduleSlots = SmClassRoutineUpdate::where('subject_id', $block->subject_id)
                                ->where('class_id', $block->class_id)
                                ->where('section_id', $block->section_id)
                                ->with('weekend')
                                ->get();

                            $taken = SmOptionalSubjectAssign::where('assign_subject_id', $block->id)->count();
                            $block->takenSlots = $taken;
                            $block->slotsLeft = $block->max_slots !== null ? max(0, $block->max_slots - $taken) : null;
                        });

                        $subject->blocks = $blocks;
                        $subject->chosenAssignSubjectId = $existingChoices->get($subject->id);
                        $subject->unmetPrerequisites = $this->unmetPrerequisites($student->id, $subject);
                    });
                }
            }

            $eligibleSubjects = $subjects->filter(fn ($s) => empty($s->unmetPrerequisites));
            $hasSubmitted = $eligibleSubjects->count() > 0 && $eligibleSubjects->every(fn ($s) => $s->chosenAssignSubjectId !== null);

            return view('backEnd.studentPanel.subjectRegistration', compact('student', 'activeSemester', 'subjects', 'hasSubmitted'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function register(Request $request)
    {
        try {
            $student = Auth::user()->student;
            $schoolId = Auth::user()->school_id;

            if (!$student || !$student->course_id || !$student->curriculum_version_id) {
                Toastr::error('You have not been assigned a program yet. Please contact the Registrar.', 'Failed');
                return redirect()->back();
            }

            $activeSemester = Semester::where('school_id', $schoolId)->where('is_active', 1)->first();
            if (!$activeSemester) {
                Toastr::error('No semester is currently open for enrollment.', 'Failed');
                return redirect()->back();
            }

            $subjects = SmSubject::where('course_id', $student->course_id)
                ->where('curriculum_version_id', $student->curriculum_version_id)
                ->where('class_id', $student->class_id)
                ->where('semester_id', $activeSemester->id)
                ->get();

            $choices = (array) $request->input('assign_subject_id', []);

            $selectedBlocks = [];
            $lockedSubjectIds = [];
            foreach ($subjects as $subject) {
                $unmet = $this->unmetPrerequisites($student->id, $subject);
                if (!empty($unmet)) {
                    // Not eligible for this subject yet - skip it entirely rather than
                    // blocking the rest of the semester's registration. They can still
                    // enroll in every other subject they do qualify for.
                    $lockedSubjectIds[] = $subject->id;
                    continue;
                }

                $assignSubjectId = $choices[$subject->id] ?? null;
                if (!$assignSubjectId) {
                    Toastr::error("Please select a block for {$subject->subject_name}.", 'Failed');
                    return redirect()->back();
                }

                $block = SmAssignSubject::where('id', $assignSubjectId)->where('subject_id', $subject->id)->first();
                if (!$block) {
                    Toastr::error('One of the selected blocks is no longer valid.', 'Failed');
                    return redirect()->back();
                }

                if ($block->max_slots !== null) {
                    $taken = SmOptionalSubjectAssign::where('assign_subject_id', $block->id)
                        ->where('student_id', '!=', $student->id)
                        ->count();
                    if ($taken >= $block->max_slots) {
                        Toastr::error("The block you selected for {$subject->subject_name} is already full.", 'Failed');
                        return redirect()->back();
                    }
                }

                $selectedBlocks[] = $block;
            }

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

            $bySubject = $schedule->groupBy('subject_id');
            $allSlots = $bySubject->flatten(1);
            foreach ($allSlots as $a) {
                foreach ($allSlots as $b) {
                    if ($a->id === $b->id || $a->subject_id === $b->subject_id) {
                        continue;
                    }
                    if ((int) $a->day !== (int) $b->day) {
                        continue;
                    }
                    if ($a->start_time < $b->end_time && $b->start_time < $a->end_time) {
                        Toastr::error('Schedule conflict detected between your selected subjects.', 'Failed');
                        return redirect()->back();
                    }
                }
            }

            $record = StudentRecord::where('school_id', $schoolId)
                ->where('student_id', $student->id)
                ->where('academic_id', getAcademicId())
                ->where('is_promote', 0)
                ->first();

            $lockedSubjectNames = $subjects->whereIn('id', $lockedSubjectIds)->pluck('subject_name')->implode(', ');

            // Don't touch existing registrations for subjects that are locked this submission -
            // a prerequisite may have been attached after the student already registered/was
            // graded for it, and that record shouldn't be silently wiped.
            $editableSubjectIds = $subjects->pluck('id')->diff($lockedSubjectIds);

            DB::transaction(function () use ($editableSubjectIds, $selectedBlocks, $student, $schoolId, $record) {
                SmOptionalSubjectAssign::where('student_id', $student->id)
                    ->whereIn('subject_id', $editableSubjectIds)
                    ->delete();

                foreach ($selectedBlocks as $block) {
                    $choice = new SmOptionalSubjectAssign();
                    $choice->student_id = $student->id;
                    $choice->record_id = optional($record)->id;
                    $choice->subject_id = $block->subject_id;
                    $choice->assign_subject_id = $block->id;
                    $choice->school_id = $schoolId;
                    $choice->session_id = getAcademicId();
                    $choice->academic_id = getAcademicId();
                    $choice->active_status = 1;
                    $choice->save();
                }

                $student->enrollment_status = 'pending';
                $student->save();
            });

            $message = 'Subjects registered. Your enrollment is pending review by the Registrar/Cashier.';
            if ($lockedSubjectNames) {
                $message .= " Not included (prerequisite not yet passed): {$lockedSubjectNames}.";
            }
            Toastr::success($message, 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * Returns the names of prerequisite subjects the student has not yet passed
     * (passed = teacher marked is_pass=1 on their SmOptionalSubjectAssign row).
     */
    private function unmetPrerequisites($studentId, SmSubject $subject)
    {
        $prerequisites = $subject->prerequisites()->with('prerequisiteSubject')->get();

        $unmet = [];
        foreach ($prerequisites as $prerequisite) {
            $passed = SmOptionalSubjectAssign::where('student_id', $studentId)
                ->where('subject_id', $prerequisite->prerequisite_subject_id)
                ->where('is_pass', 1)
                ->exists();

            if (!$passed) {
                $unmet[] = optional($prerequisite->prerequisiteSubject)->subject_name ?? 'Unknown subject';
            }
        }

        return $unmet;
    }

    public function balanceSummary($state = 'view')
    {
        try {
            $student = Auth::user()->student;

            if (!$student || !$student->course_id) {
                Toastr::error('You have not been assigned a program yet.', 'Failed');
                return redirect()->back();
            }

            $breakdown = $this->balanceBreakdownFor($student);
            $data = array_merge($breakdown, [
                'student' => $student,
                'printUrl' => route('student-balance-summary', ['state' => 'print']),
            ]);

            return $state == 'print'
                ? view('backEnd.academics.balanceSummaryPrint', $data)
                : view('backEnd.academics.balanceSummary', $data);
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
