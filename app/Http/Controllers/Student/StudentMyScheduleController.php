<?php

namespace App\Http\Controllers\Student;

use App\Semester;
use App\SmSubject;
use App\SmWeekend;
use App\SmAssignSubject;
use App\SmClassRoutineUpdate;
use App\SmOptionalSubjectAssign;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;

class StudentMyScheduleController extends Controller
{
    public function index(Request $request)
    {
        try {
            $student = Auth::user()->student;
            $schoolId = Auth::user()->school_id;

            $subjects = collect();
            $totalUnits = 0;
            $activeSemester = null;
            $smWeekends = SmWeekend::where('school_id', $schoolId)
                ->where('active_status', 1)
                ->orderBy('order', 'ASC')
                ->get();
            $grid = [];
            $times = collect();

            if ($student && $student->course_id && $student->curriculum_version_id && $student->class_id) {
                $activeSemester = Semester::where('school_id', $schoolId)->where('is_active', 1)->first();

                if ($activeSemester) {
                    $subjects = SmSubject::where('course_id', $student->course_id)
                        ->where('curriculum_version_id', $student->curriculum_version_id)
                        ->where('class_id', $student->class_id)
                        ->where('semester_id', $activeSemester->id)
                        ->with(['yearLevel', 'semester'])
                        ->get();

                    $chosenAssignSubjectIds = SmOptionalSubjectAssign::where('student_id', $student->id)
                        ->whereIn('subject_id', $subjects->pluck('id'))
                        ->pluck('assign_subject_id')
                        ->filter()
                        ->values();

                    $chosenBlocks = SmAssignSubject::whereIn('id', $chosenAssignSubjectIds)->get();

                    $routines = collect();
                    if ($chosenBlocks->isNotEmpty()) {
                        $routines = SmClassRoutineUpdate::where(function ($q) use ($chosenBlocks) {
                            foreach ($chosenBlocks as $block) {
                                $q->orWhere(function ($q2) use ($block) {
                                    $q2->where('subject_id', $block->subject_id)
                                        ->where('class_id', $block->class_id)
                                        ->where('section_id', $block->section_id);
                                });
                            }
                        })
                            ->with(['weekend', 'teacherDetail', 'classRoom', 'subject', 'section'])
                            ->orderBy('start_time')
                            ->get();
                    }

                    $subjects->each(function ($subject) use ($routines) {
                        $subject->scheduleSlots = $routines->where('subject_id', $subject->id)->values();
                    });

                    // Align rows by actual clock time (not row position) so every day's
                    // column lines up under the same time slot, like a real timetable.
                    $times = $routines->pluck('start_time')->unique()->sort()->values();

                    foreach ($routines as $routine) {
                        $grid[$routine->start_time][$routine->day] = $routine;
                    }

                    $totalUnits = $subjects->sum('units');
                }
            }

            return view('backEnd.studentPanel.mySchedule', compact(
                'student',
                'subjects',
                'totalUnits',
                'activeSemester',
                'smWeekends',
                'grid',
                'times'
            ));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
