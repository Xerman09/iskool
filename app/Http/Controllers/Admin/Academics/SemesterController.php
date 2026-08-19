<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Semester;
use App\tableList;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use App\Http\Requests\Admin\Academics\SemesterRequest;

class SemesterController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $semesters = Semester::where('school_id', auth()->user()->school_id)->orderBy('sort_order')->orderBy('id')->get();
            return view('backEnd.academics.semester', compact('semesters'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(SemesterRequest $request)
    {
        try {
            $semester = new Semester();
            $semester->semester_name = $request->semester_name;
            $semester->sort_order = $request->sort_order;
            $semester->active_status = 1;
            $semester->school_id = auth()->user()->school_id;
            $semester->save();

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
            $semester = Semester::where('school_id', auth()->user()->school_id)->findOrFail($id);
            $semesters = Semester::where('school_id', auth()->user()->school_id)->orderBy('sort_order')->orderBy('id')->get();
            return view('backEnd.academics.semester', compact('semester', 'semesters'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(SemesterRequest $request)
    {
        try {
            $semester = Semester::where('school_id', auth()->user()->school_id)->findOrFail($request->id);
            $semester->semester_name = $request->semester_name;
            $semester->sort_order = $request->sort_order;
            $semester->save();

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('semester');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $tables = tableList::getTableList('semester_id', $id);
            if ($tables == null) {
                Semester::where('school_id', auth()->user()->school_id)->where('id', $id)->delete();
                Toastr::success('Operation successful', 'Success');
                return redirect()->route('semester');
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
