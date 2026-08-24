<?php

namespace App\Http\Controllers\Student;

use App\SmSubject;
use App\SmClassRoutineUpdate;
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
            $subjects = collect();
            $totalUnits = 0;

            if ($student && $student->course_id && $student->class_id && $student->semester_id) {
                $subjects = SmSubject::where('course_id', $student->course_id)
                    ->where('curriculum_version_id', $student->curriculum_version_id)
                    ->where('class_id', $student->class_id)
                    ->where('semester_id', $student->semester_id)
                    ->with(['yearLevel', 'semester'])
                    ->get();

                $schedulesBySubject = SmClassRoutineUpdate::whereIn('subject_id', $subjects->pluck('id'))
                    ->with(['weekend', 'teacherDetail', 'section'])
                    ->get()
                    ->groupBy('subject_id');

                $subjects->each(function ($subject) use ($schedulesBySubject) {
                    $subject->scheduleSlots = $schedulesBySubject->get($subject->id, collect());
                });

                $totalUnits = $subjects->sum('units');
            }

            return view('backEnd.studentPanel.mySchedule', compact('student', 'subjects', 'totalUnits'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
