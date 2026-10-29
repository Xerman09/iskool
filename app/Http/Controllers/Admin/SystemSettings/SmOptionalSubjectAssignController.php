<?php

namespace App\Http\Controllers\Admin\SystemSettings;

use App\SmClass;
use App\SmStaff;
use App\SmStudent;
use App\SmSubject;
use App\SmClassSection;
use App\SmAssignSubject;
use Illuminate\Http\Request;
use App\Models\StudentRecord;
use App\SmClassOptionalSubject;
use App\SmOptionalSubjectAssign;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Admin\Academics\AssignOptionalSubjectSearch;
use App\Http\Requests\Admin\GeneralSettings\SmOptionalSetupStoreRequest;

class SmOptionalSubjectAssignController extends Controller
{

    public function __construct()
    {
        $this->middleware('PM');
        // User::checkAuth();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function assignOptionalSubject(Request $request)
    {
        Toastr::error('Operation Failed', 'Failed');
        return redirect()->back();
    }
    public function index(Request $request)
    {

        $classes = SmClass::get();
        $sections = SmClassSection::get();
        $assign_subjects = SmAssignSubject::get();
        $subjects = SmSubject::get();
        $teachers = SmStaff::where('role_id', 4)->get();
        return view('backEnd.academics.assign_optional_subject', compact('classes', 'sections', 'assign_subjects', 'subjects', 'teachers'));
    }

    public function assignOptionalSubjectSearch(AssignOptionalSubjectSearch $request)
    {
        try {
            $students = StudentRecord::with('studentDetail','studentDetail.subjectAssign', 'studentDetail.subjectAssign.subject')
            ->whereHas('studentDetail', function($q) use ($request){
                return $q->where('active_status', 1)
                    ->when($request->filled('course_id'), function ($q2) use ($request) {
                        $q2->where('course_id', $request->course_id);
                    })
                    ->when($request->filled('semester_id'), function ($q2) use ($request) {
                        $q2->where('semester_id', $request->semester_id);
                    });
            })
                        ->where('class_id', $request->class_id)
                        ->where('section_id', $request->section_id)
                        ->where('academic_id', getAcademicId())
                        ->where('is_promote', 0)
                        ->where('school_id', Auth::user()->school_id)
                        ->get();

            $subject_id = $request->subject_id;
            $subject_info = SmSubject::where('id', '=', $request->subject_id)->first();
            $subjects = SmSubject::where('active_status', 1)->where('school_id', Auth::user()->school_id)->get();
            $teachers = SmStaff::where('active_status', 1)->where(function($q)  {
	        $q->where('role_id', 4)->orWhere('previous_role_id', 4);})->where('school_id', Auth::user()->school_id)->get();

            $class_id = $request->class_id;
            $section_id = $request->section_id;
            $classes = SmClass::get();
            $class = SmClass::with('classSection')->where('id', $class_id)->first();
            $assignSubjects= SmAssignSubject::with('subject')->where('class_id', $class_id)->where('section_id', $section_id)->get();
            return view('backEnd.academics.assign_optional_subject', compact('classes', 'teachers', 'subjects', 'class_id', 'section_id', 'students', 'subject_id', 'subject_info', 'class', 'assignSubjects'));
        } catch (\Exception $e) { 
             
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
    /**
     * Put a chosen set of students into one subject block.
     *
     * The screen edits exactly one block - this subject, for this class and section, in
     * this academic year - and everything here is scoped to it. What it replaced deleted
     * by subject alone across the whole year, so assigning one section emptied every
     * other section of that subject, and took with it the registrations students had made
     * for themselves. It also wrote no assign_subject_id, which left the rows invisible
     * to attendance, exams and marks, all of which reach students through the block.
     */
    public function assignOptionalSubjectStore(Request $request)
    {
        try {
            $schoolId = Auth::user()->school_id;

            $block = SmAssignSubject::where('class_id', $request->class_id)
                ->where('section_id', $request->section_id)
                ->where('subject_id', $request->subject_id)
                ->where('academic_id', getAcademicId())
                ->where('school_id', $schoolId)
                ->first();

            // No block means no teacher, no schedule and nothing for attendance to hang
            // off - a row written here would simply never be seen again.
            if (!$block) {
                Toastr::error('Assign this subject to the class and section first.', 'Failed');
                return redirect('optional-subject');
            }

            $submitted = collect($request->student_id ?? [])
                ->filter()
                ->map(fn ($id) => (int) $id);

            if ($submitted->isEmpty()) {
                Toastr::warning('No Student Select', 'Warning');
                return redirect('optional-subject');
            }

            $existing = SmOptionalSubjectAssign::where('assign_subject_id', $block->id)
                ->where('school_id', $schoolId)
                ->get();

            /* Unticking a student takes them out of this block. A row that has already
               been graded is left alone: that is a result, not an enrolment choice, and
               the prerequisite gate reads it for the rest of the student's stay. */
            $remove = $existing->filter(function ($row) use ($submitted) {
                return !$submitted->contains((int) $row->record_id) && $row->is_pass === null;
            });

            if ($remove->isNotEmpty()) {
                SmOptionalSubjectAssign::whereIn('id', $remove->pluck('id'))->delete();
            }

            $alreadyIn = $existing->pluck('record_id')->filter()->map(fn ($id) => (int) $id);

            foreach ($submitted->diff($alreadyIn) as $recordId) {
                $record = StudentRecord::where('id', $recordId)
                    ->where('school_id', $schoolId)
                    ->first();

                if (!$record) {
                    continue;
                }

                $optional_subject = new SmOptionalSubjectAssign();
                $optional_subject->student_id = $record->student_id;
                $optional_subject->record_id = $record->id;
                $optional_subject->subject_id = $block->subject_id;
                $optional_subject->assign_subject_id = $block->id;
                $optional_subject->session_id = $record->session_id;
                $optional_subject->created_by = Auth::user()->id;
                $optional_subject->school_id = $schoolId;
                $optional_subject->academic_id = getAcademicId();
                $optional_subject->active_status = 1;
                $optional_subject->save();
            }

            Toastr::success('Operation successful', 'Success');
            return redirect('optional-subject');
        } catch (\Throwable $th) {
            Log::error('assignOptionalSubjectStore: ' . $th->getMessage());
            Toastr::error('Operation Failed', 'Failed');
            return redirect('optional-subject');
        }
    }
    public function optionalSetup(Request $request)
    {

        try {
            $classes = SmClass::where('active_status', 1)->where('school_id', Auth::user()->school_id)->get();
            $class_optionals = SmClassOptionalSubject::join('sm_classes', 'sm_classes.id', '=', 'sm_class_optional_subject.class_id')
                ->select('sm_class_optional_subject.*', 'class_name')
                ->where('sm_class_optional_subject.school_id', Auth::user()->school_id)
                ->where('sm_class_optional_subject.academic_id', getAcademicId())
                ->orderby('sm_class_optional_subject.id', 'DESC')
                ->get();
            return view('backEnd.systemSettings.optional_subject_setup', compact('classes', 'class_optionals'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
    public function optionalSetupStore(SmOptionalSetupStoreRequest $request)
    {

        try {
            foreach ($request->class as $value) {
                $optional_check = SmClassOptionalSubject::where('class_id', '=', $value)->first();
                if ($optional_check == '') {
                    $class_optional = new SmClassOptionalSubject();
                    $class_optional->class_id = $value;
                } else {
                    $class_optional = SmClassOptionalSubject::where('class_id', '=', $value)->first();
                }
                $class_optional->gpa_above = $request->gpa_above;
                $class_optional->school_id = Auth::user()->school_id;
                $class_optional->created_by = Auth::user()->id;
                $class_optional->updated_by = Auth::user()->id;
                $class_optional->academic_id = getAcademicId();
                $class_optional->save();
            }
            Toastr::success('Operation successful', 'Success');
            return redirect('optional-subject-setup');
        } catch (\Throwable $th) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
    public function optionalSetupDelete($id)
    {

        try {
            $class_optional = SmClassOptionalSubject::findOrfail($id);
            $class_optional->delete();
            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Throwable $th) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
    public function optionalSetupEdit($id)
    {

        try {
            $editData = SmClassOptionalSubject::findOrfail($id);
            $classes = SmClass::where('active_status', 1)->where('school_id', Auth::user()->school_id)->get();
            //    return $classes;
            $class_optionals = SmClassOptionalSubject::join('sm_classes', 'sm_classes.id', '=', 'sm_class_optional_subject.class_id')
                ->select('sm_class_optional_subject.*', 'class_name')
                ->where('sm_class_optional_subject.school_id', Auth::user()->school_id)->get();
            return view('backEnd.systemSettings.optional_subject_setup', compact('classes', 'class_optionals', 'editData'));
        } catch (\Throwable $th) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
