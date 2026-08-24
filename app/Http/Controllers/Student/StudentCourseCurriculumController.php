<?php

namespace App\Http\Controllers\Student;

use App\Course;
use App\CurriculumVersion;
use App\SmSubject;
use App\SmClassRoutineUpdate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Modules\Fees\Entities\FmFeesType;

class StudentCourseCurriculumController extends Controller
{
    public function index(Request $request)
    {
        try {
            $student = Auth::user()->student;
            $schoolId = Auth::user()->school_id;

            $courses = Course::where('school_id', $schoolId)->get();
            $curriculumVersions = CurriculumVersion::where('school_id', $schoolId)->get();

            $courseId = $request->course_id ?: optional($student)->course_id;
            $curriculumVersionId = $request->curriculum_version_id ?: optional($student)->curriculum_version_id;

            $course = null;
            $curriculumVersion = null;
            $years = null;
            $grandTotalUnits = 0;
            $grandTotal = 0;

            if ($courseId && $curriculumVersionId) {
                $course = Course::where('school_id', $schoolId)->find($courseId);
                $curriculumVersion = CurriculumVersion::where('school_id', $schoolId)->find($curriculumVersionId);
            }

            if ($course && $curriculumVersion) {
                $pricePerUnit = $course->price_per_unit ?: 0;

                $subjects = SmSubject::where('course_id', $course->id)
                    ->where('curriculum_version_id', $curriculumVersion->id)
                    ->with(['yearLevel', 'semester'])
                    ->get();

                $schedulesBySubject = SmClassRoutineUpdate::whereIn('subject_id', $subjects->pluck('id'))
                    ->with(['weekend', 'teacherDetail', 'section'])
                    ->get()
                    ->groupBy('subject_id');

                $subjects->each(function ($subject) use ($schedulesBySubject) {
                    $subject->scheduleSlots = $schedulesBySubject->get($subject->id, collect());
                });

                $years = $subjects->groupBy('class_id')->map(function ($yearSubjects, $classId) use ($pricePerUnit, $course, $schoolId) {
                    $semesters = $yearSubjects->groupBy('semester_id')->map(function ($semSubjects, $semesterId) use ($pricePerUnit, $course, $classId, $schoolId) {
                        $units = $semSubjects->sum('units');
                        $unitsPrice = $units * $pricePerUnit;

                        $miscFees = FmFeesType::where('school_id', $schoolId)
                            ->where('course_id', $course->id)
                            ->where('class_id', $classId)
                            ->where('semester_id', $semesterId)
                            ->get();
                        $miscTotal = $miscFees->sum('amount');

                        return [
                            'semester' => optional($semSubjects->first()->semester),
                            'subjects' => $semSubjects,
                            'units' => $units,
                            'unitsPrice' => $unitsPrice,
                            'miscFees' => $miscFees,
                            'miscTotal' => $miscTotal,
                            'semTotal' => $unitsPrice + $miscTotal,
                        ];
                    });

                    $yearTotal = $semesters->sum('semTotal');

                    return [
                        'yearLevel' => optional($yearSubjects->first()->yearLevel),
                        'semesters' => $semesters,
                        'units' => $yearSubjects->sum('units'),
                        'yearTotal' => $yearTotal,
                    ];
                })->sortKeys();

                foreach ($years as $year) {
                    $grandTotalUnits += $year['units'];
                    $grandTotal += $year['yearTotal'];
                }
            }

            return view('backEnd.studentPanel.courseCurriculum', compact(
                'courses',
                'curriculumVersions',
                'course',
                'curriculumVersion',
                'years',
                'grandTotalUnits',
                'grandTotal',
                'student'
            ));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function enroll(Request $request)
    {
        try {
            $request->validate([
                'course_id' => 'required|exists:courses,id',
                'curriculum_version_id' => 'required|exists:curriculum_versions,id',
                'payment_plan' => 'required|in:per_semester,per_year',
            ]);

            $student = Auth::user()->student;
            $student->course_id = $request->course_id;
            $student->curriculum_version_id = $request->curriculum_version_id;
            $student->payment_plan = $request->payment_plan;
            if ($student->enrollment_status === null) {
                $student->enrollment_status = 'pending';
            }
            $student->save();

            Toastr::success('Enrollment request submitted. Please proceed to the Registrar/Cashier to complete payment.', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
