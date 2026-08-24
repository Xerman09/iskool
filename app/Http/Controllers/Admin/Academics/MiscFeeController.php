<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\SmClass;
use App\Semester;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesType;
use Modules\Fees\Entities\FmFeesGroup;
use Modules\Fees\Entities\FmFeesInvoiceChield;

class MiscFeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $courses = Course::where('school_id', auth()->user()->school_id)->get();
            $classes = SmClass::where('school_id', auth()->user()->school_id)->get();
            $semesters = Semester::where('school_id', auth()->user()->school_id)->get();

            $course = null;
            $class = null;
            $semester = null;
            $miscFees = null;

            if ($request->filled(['course_id', 'class_id', 'semester_id'])) {
                $course = Course::where('school_id', auth()->user()->school_id)->findOrFail($request->course_id);
                $class = SmClass::where('school_id', auth()->user()->school_id)->findOrFail($request->class_id);
                $semester = Semester::where('school_id', auth()->user()->school_id)->findOrFail($request->semester_id);

                $miscFees = FmFeesType::where('school_id', auth()->user()->school_id)
                    ->where('course_id', $course->id)
                    ->where('class_id', $class->id)
                    ->where('semester_id', $semester->id)
                    ->get();
            }

            return view('backEnd.academics.miscFeeAssign', compact('courses', 'classes', 'semesters', 'course', 'class', 'semester', 'miscFees'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    private function miscFeesGroup()
    {
        $group = FmFeesGroup::where('school_id', auth()->user()->school_id)
            ->where('academic_id', getAcademicId())
            ->where('name', 'Miscellaneous Fees')
            ->first();

        if (!$group) {
            $group = new FmFeesGroup();
            $group->name = 'Miscellaneous Fees';
            $group->description = 'Auto-created group for program/year-scoped miscellaneous fees.';
            $group->school_id = auth()->user()->school_id;
            $group->academic_id = getAcademicId();
            $group->save();
        }

        return $group;
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'course_id' => 'required|exists:courses,id',
                'class_id' => 'required|exists:sm_classes,id',
                'semester_id' => 'required|exists:semesters,id',
                'name' => 'required|max:50',
                'amount' => 'required|numeric|min:0',
            ]);

            $feesType = new FmFeesType();
            $feesType->name = $request->name;
            $feesType->fees_group_id = $this->miscFeesGroup()->id;
            $feesType->type = 'fees';
            $feesType->course_id = $request->course_id;
            $feesType->class_id = $request->class_id;
            $feesType->semester_id = $request->semester_id;
            $feesType->amount = $request->amount;
            $feesType->school_id = auth()->user()->school_id;
            $feesType->academic_id = getAcademicId();
            $feesType->save();

            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:fm_fees_types,id',
                'name' => 'required|max:50',
                'amount' => 'required|numeric|min:0',
            ]);

            $feesType = FmFeesType::where('school_id', auth()->user()->school_id)
                ->whereNotNull('course_id')
                ->whereNotNull('class_id')
                ->whereNotNull('semester_id')
                ->findOrFail($request->id);
            $feesType->name = $request->name;
            $feesType->amount = $request->amount;
            $feesType->save();

            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $checkExistsData = FmFeesInvoiceChield::where('fees_type', $id)->first();

            if ($checkExistsData) {
                Toastr::error('This fee is already used in an invoice and cannot be deleted.', 'Failed');
                return redirect()->back();
            }

            FmFeesType::where('school_id', auth()->user()->school_id)
                ->whereNotNull('course_id')
                ->whereNotNull('class_id')
                ->whereNotNull('semester_id')
                ->where('id', $id)
                ->delete();

            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
