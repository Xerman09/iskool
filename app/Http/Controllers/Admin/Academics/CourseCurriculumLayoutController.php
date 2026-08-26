<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\SmSubject;
use App\CurriculumVersion;
use App\SmClassRoutineUpdate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesType;

class CourseCurriculumLayoutController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $courses = Course::where('school_id', auth()->user()->school_id)->get();
            $curriculumVersions = CurriculumVersion::where('school_id', auth()->user()->school_id)->get();

            $course = null;
            $curriculumVersion = null;
            $years = null;
            $grandTotalUnits = 0;
            $grandTotal = 0;
            $printUrl = null;

            if ($request->filled(['course_id', 'curriculum_version_id'])) {
                $course = Course::where('school_id', auth()->user()->school_id)->findOrFail($request->course_id);
                $curriculumVersion = CurriculumVersion::where('school_id', auth()->user()->school_id)->findOrFail($request->curriculum_version_id);

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

                $years = $subjects->groupBy('class_id')->map(function ($yearSubjects, $classId) use ($pricePerUnit, $course) {
                    $semesters = $yearSubjects->groupBy('semester_id')->map(function ($semSubjects, $semesterId) use ($pricePerUnit, $course, $classId) {
                        $units = $semSubjects->sum('units');
                        $unitsPrice = $units * $pricePerUnit;

                        $miscFees = FmFeesType::where('school_id', auth()->user()->school_id)
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

                $printUrl = route('curriculum-layout', [
                    'course_id' => $course->id,
                    'curriculum_version_id' => $curriculumVersion->id,
                    'state' => 'print',
                ]);
            }

            $data = compact(
                'courses',
                'curriculumVersions',
                'course',
                'curriculumVersion',
                'years',
                'grandTotalUnits',
                'grandTotal',
                'printUrl'
            );

            if ($request->get('state') == 'print' && $course && $curriculumVersion) {
                return view('backEnd.academics.courseCurriculumLayoutPrint', $data);
            }

            return view('backEnd.academics.courseCurriculumLayout', $data);
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
