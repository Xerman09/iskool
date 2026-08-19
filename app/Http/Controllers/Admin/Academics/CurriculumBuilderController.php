<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\Semester;
use App\SmClass;
use App\SmSubject;
use App\tableList;
use App\CurriculumVersion;
use App\SubjectPrerequisite;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use App\Http\Requests\Admin\Academics\CurriculumBuilderRequest;

class CurriculumBuilderController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    private $criteriaFields = ['course_id', 'class_id', 'semester_id', 'curriculum_version_id'];

    private function formOptions()
    {
        return [
            'courses' => Course::where('school_id', auth()->user()->school_id)->get(),
            'curriculumVersions' => CurriculumVersion::where('school_id', auth()->user()->school_id)->get(),
            'classes' => SmClass::where('school_id', auth()->user()->school_id)->get(),
            'semesters' => Semester::where('school_id', auth()->user()->school_id)->get(),
            'prerequisiteOptions' => SmSubject::whereNotNull('course_id')->get(),
            'baseSubjects' => SmSubject::whereNull('course_id')->get(),
        ];
    }

    private function syncPrerequisites(Request $request, SmSubject $subject)
    {
        SubjectPrerequisite::where('subject_id', $subject->id)
            ->where('school_id', auth()->user()->school_id)->delete();

        if ($request->boolean('has_prerequisite') && $request->prerequisite_subject_ids) {
            foreach ($request->prerequisite_subject_ids as $prerequisiteId) {
                SubjectPrerequisite::create([
                    'subject_id' => $subject->id,
                    'prerequisite_subject_id' => $prerequisiteId,
                    'school_id' => auth()->user()->school_id,
                ]);
            }
        }
    }

    public function index(Request $request)
    {
        try {
            $data = $this->formOptions();
            $subjects = null;
            $criteria = null;

            if ($request->hasAny($this->criteriaFields)) {
                $validator = \Validator::make($request->all(), [
                    'course_id' => ['required', 'exists:courses,id'],
                    'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
                    'class_id' => ['required', 'exists:sm_classes,id'],
                    'semester_id' => ['required', 'exists:semesters,id'],
                ]);

                if ($validator->fails()) {
                    return view('backEnd.academics.curriculumBuilder', array_merge($data, compact('subjects', 'criteria')))
                        ->withErrors($validator);
                }

                $criteria = $request->only($this->criteriaFields);
                $subjects = SmSubject::where($criteria)
                    ->with(['prerequisites.prerequisiteSubject'])
                    ->orderBy('id', 'DESC')->get();
                $data = array_merge($data, $this->formOptions());
            }

            return view('backEnd.academics.curriculumBuilder', array_merge($data, compact('subjects', 'criteria')));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(CurriculumBuilderRequest $request)
    {
        try {
            $sourceSubject = SmSubject::whereNull('course_id')->findOrFail($request->source_subject_id);

            $subject = new SmSubject();
            $subject->course_id = $request->course_id;
            $subject->curriculum_version_id = $request->curriculum_version_id;
            $subject->class_id = $request->class_id;
            $subject->semester_id = $request->semester_id;
            $subject->source_subject_id = $sourceSubject->id;
            $subject->subject_name = $sourceSubject->subject_name;
            $subject->subject_code = $sourceSubject->subject_code;
            $subject->units = $request->units;
            $subject->subject_classification = $request->subject_classification;
            $subject->subject_type = 'T';
            $subject->created_by = auth()->user()->id;
            $subject->school_id = auth()->user()->school_id;
            $subject->academic_id = getAcademicId();
            $subject->save();
            $this->syncPrerequisites($request, $subject);

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('curriculum-builder', $request->only($this->criteriaFields));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(CurriculumBuilderRequest $request)
    {
        try {
            $sourceSubject = SmSubject::whereNull('course_id')->findOrFail($request->source_subject_id);
            $subject = SmSubject::whereNotNull('course_id')->findOrFail($request->id);
            $subject->course_id = $request->course_id;
            $subject->curriculum_version_id = $request->curriculum_version_id;
            $subject->class_id = $request->class_id;
            $subject->semester_id = $request->semester_id;
            $subject->source_subject_id = $sourceSubject->id;
            $subject->subject_name = $sourceSubject->subject_name;
            $subject->subject_code = $sourceSubject->subject_code;
            $subject->units = $request->units;
            $subject->subject_classification = $request->subject_classification;
            $subject->updated_by = auth()->user()->id;
            $subject->save();
            $this->syncPrerequisites($request, $subject);

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('curriculum-builder', $request->only($this->criteriaFields));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $subject = SmSubject::whereNotNull('course_id')->findOrFail($id);
            $criteria = $subject->only($this->criteriaFields);

            $tables = tableList::getTableList('subject_id', $id);
            if ($tables == null) {
                $subject->delete();
                Toastr::success('Operation successful', 'Success');
                return redirect()->route('curriculum-builder', $criteria);
            }

            $msg = 'This data already used in : ' . $tables . ' Please remove those data first';
            Toastr::error($msg, 'Failed');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
