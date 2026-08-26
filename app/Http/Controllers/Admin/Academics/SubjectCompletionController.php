<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\SmClass;
use App\Semester;
use App\SmSubject;
use App\CurriculumVersion;
use App\SmClassSection;
use App\SmAssignSubject;
use Illuminate\Http\Request;
use App\SmOptionalSubjectAssign;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SubjectCompletionController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $classes = SmClass::get();
            $courses = Course::where('school_id', Auth::user()->school_id)->get();
            $curriculumVersions = CurriculumVersion::where('school_id', Auth::user()->school_id)->get();
            $semesters = Semester::where('school_id', Auth::user()->school_id)->get();

            return view('backEnd.academics.subject_completion', compact('classes', 'courses', 'curriculumVersions', 'semesters'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'curriculum_version_id' => 'required|exists:curriculum_versions,id',
            'class' => 'required',
            'semester_id' => 'required|exists:semesters,id',
            'section' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $classes = SmClass::get();
            $courses = Course::where('school_id', Auth::user()->school_id)->get();
            $curriculumVersions = CurriculumVersion::where('school_id', Auth::user()->school_id)->get();
            $semesters = Semester::where('school_id', Auth::user()->school_id)->get();
            $sections = SmClassSection::where('class_id', $request->class)->with('sectionName', 'className')
                ->where('section_id', $request->section)->get();

            $class_id = $request->class;
            $section_id = $request->section;
            $course_id = $request->course_id;
            $curriculum_version_id = $request->curriculum_version_id;
            $semester_id = $request->semester_id;

            // Curriculum-driven: same subject list as Assign Subject / Curriculum Layout.
            $subjects = SmSubject::whereNotNull('course_id')
                ->where('course_id', $course_id)
                ->where('curriculum_version_id', $curriculum_version_id)
                ->where('class_id', $class_id)
                ->where('semester_id', $semester_id)
                ->get();

            $subjects->each(function ($subject) use ($class_id, $section_id) {
                $blockIds = SmAssignSubject::where('subject_id', $subject->id)
                    ->where('class_id', $class_id)
                    ->where('section_id', $section_id)
                    ->pluck('id');

                $subject->enrolledStudents = SmOptionalSubjectAssign::with('assignSubject')
                    ->whereIn('assign_subject_id', $blockIds)
                    ->get()
                    ->map(function ($assign) {
                        $assign->studentDetail = \App\SmStudent::find($assign->student_id);
                        return $assign;
                    })
                    ->filter(fn ($assign) => $assign->studentDetail);
            });

            return view('backEnd.academics.subject_completion', compact(
                'classes', 'courses', 'curriculumVersions', 'semesters', 'sections',
                'class_id', 'section_id', 'course_id', 'curriculum_version_id', 'semester_id', 'subjects'
            ));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(Request $request)
    {
        try {
            foreach ($request->is_pass ?? [] as $optionalSubjectAssignId => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }

                SmOptionalSubjectAssign::where('id', $optionalSubjectAssignId)
                    ->where('school_id', Auth::user()->school_id)
                    ->update(['is_pass' => (int) $value]);
            }

            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
