<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\tableList;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use App\Http\Requests\Admin\Academics\CourseRequest;

class CourseController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $courses = Course::where('school_id', auth()->user()->school_id)->orderBy('id', 'DESC')->get();
            return view('backEnd.academics.course', compact('courses'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(CourseRequest $request)
    {
        try {
            $course = new Course();
            $course->course_name = $request->course_name;
            $course->course_code = $request->course_code;
            $course->description = $request->description;
            $course->active_status = 1;
            $course->created_by = auth()->user()->id;
            $course->school_id = auth()->user()->school_id;
            $course->save();

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
            $course = Course::where('school_id', auth()->user()->school_id)->findOrFail($id);
            $courses = Course::where('school_id', auth()->user()->school_id)->orderBy('id', 'DESC')->get();
            return view('backEnd.academics.course', compact('course', 'courses'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(CourseRequest $request)
    {
        try {
            $course = Course::where('school_id', auth()->user()->school_id)->findOrFail($request->id);
            $course->course_name = $request->course_name;
            $course->course_code = $request->course_code;
            $course->description = $request->description;
            $course->updated_by = auth()->user()->id;
            $course->save();

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('program');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $tables = tableList::getTableList('course_id', $id);
            if ($tables == null) {
                Course::where('school_id', auth()->user()->school_id)->where('id', $id)->delete();
                Toastr::success('Operation successful', 'Success');
                return redirect()->route('program');
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
