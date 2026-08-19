<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\tableList;
use App\SmAcademicYear;
use Illuminate\Http\Request;
use App\CurriculumVersion;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use App\Http\Requests\Admin\Academics\CurriculumVersionRequest;

class CurriculumVersionController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $curriculumVersions = CurriculumVersion::where('school_id', auth()->user()->school_id)->with('course')->orderBy('id', 'DESC')->get();
            $courses = Course::where('school_id', auth()->user()->school_id)->get();
            $academicYears = SmAcademicYear::where('school_id', auth()->user()->school_id)->get();
            return view('backEnd.academics.curriculumVersion', compact('curriculumVersions', 'courses', 'academicYears'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(CurriculumVersionRequest $request)
    {
        try {
            $curriculumVersion = new CurriculumVersion();
            $curriculumVersion->course_id = $request->course_id;
            $curriculumVersion->version_label = $request->version_label;
            $curriculumVersion->effective_academic_id = $request->effective_academic_id;
            $curriculumVersion->is_active = $request->boolean('is_active') ? 1 : 0;
            $curriculumVersion->created_by = auth()->user()->id;
            $curriculumVersion->school_id = auth()->user()->school_id;
            $curriculumVersion->save();

            if ($curriculumVersion->is_active) {
                $this->deactivateSiblings($curriculumVersion);
            }

            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            $curriculumVersion = CurriculumVersion::where('school_id', auth()->user()->school_id)->findOrFail($id);
            $curriculumVersions = CurriculumVersion::where('school_id', auth()->user()->school_id)->with('course')->orderBy('id', 'DESC')->get();
            $courses = Course::where('school_id', auth()->user()->school_id)->get();
            $academicYears = SmAcademicYear::where('school_id', auth()->user()->school_id)->get();
            return view('backEnd.academics.curriculumVersion', compact('curriculumVersion', 'curriculumVersions', 'courses', 'academicYears'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(CurriculumVersionRequest $request)
    {
        try {
            $curriculumVersion = CurriculumVersion::where('school_id', auth()->user()->school_id)->findOrFail($request->id);
            $curriculumVersion->course_id = $request->course_id;
            $curriculumVersion->version_label = $request->version_label;
            $curriculumVersion->effective_academic_id = $request->effective_academic_id;
            $curriculumVersion->is_active = $request->boolean('is_active') ? 1 : 0;
            $curriculumVersion->updated_by = auth()->user()->id;
            $curriculumVersion->save();

            if ($curriculumVersion->is_active) {
                $this->deactivateSiblings($curriculumVersion);
            }

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('curriculum-version');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function activate(Request $request, $id)
    {
        try {
            $curriculumVersion = CurriculumVersion::where('school_id', auth()->user()->school_id)->findOrFail($id);
            $curriculumVersion->is_active = 1;
            $curriculumVersion->save();
            $this->deactivateSiblings($curriculumVersion);

            Toastr::success('Curriculum version activated', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $tables = tableList::getTableList('curriculum_version_id', $id);
            if ($tables == null) {
                CurriculumVersion::where('school_id', auth()->user()->school_id)->where('id', $id)->delete();
                Toastr::success('Operation successful', 'Success');
                return redirect()->route('curriculum-version');
            }

            $msg = 'This data already used in : ' . $tables . ' Please remove those data first';
            Toastr::error($msg, 'Failed');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * Only one curriculum version per course should stay active at a time,
     * since newly-admitted students get anchored to whichever version is active.
     */
    private function deactivateSiblings(CurriculumVersion $curriculumVersion)
    {
        CurriculumVersion::where('course_id', $curriculumVersion->course_id)
            ->where('school_id', $curriculumVersion->school_id)
            ->where('id', '!=', $curriculumVersion->id)
            ->update(['is_active' => 0]);
    }
}
