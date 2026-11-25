<?php

namespace App\Http\Controllers\Admin\Academics;

use App\SmClass;
use App\ClassTuition;
use App\SmAcademicYear;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * What each year level costs in tuition.
 *
 * Basic education's counterpart to the price per unit on a course. One screen
 * listing every year level the school runs, one amount against each, saved
 * together - a registrar setting up a school enters the whole fee structure in
 * one pass rather than opening a form per grade.
 *
 * Tuition only. Everything else a school charges - laboratory, ID, uniform -
 * stays in Assign Misc Fee, which is what that screen is named for.
 */
class ClassTuitionController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            /* A college prices its tuition on the course, per unit. Offering this
               screen there would be a second place to set the same number, which
               is the fault this screen exists to undo. */
            if (!isBasicEd()) {
                Toastr::warning(__('academics.tuition_setup_basic_ed_only'), 'Warning');

                return redirect()->route('dashboard');
            }

            $schoolId = auth()->user()->school_id;

            $years = SmAcademicYear::where('school_id', $schoolId)->orderByDesc('id')->get();

            $academicId = $request->filled('academic_id')
                ? (int) $request->academic_id
                : (int) getAcademicId();

            /* Ordered by id, because sm_classes carries no sort column of its own.
               A school entering Grade 7 through Grade 12 enters them in that order,
               so id follows the years; the view says so rather than leaving the
               reader to wonder why the list reads oddly. */
            $classes = SmClass::where('school_id', $schoolId)
                ->where('active_status', 1)
                ->orderBy('id')
                ->get();

            $amounts = ClassTuition::where('school_id', $schoolId)
                ->where('academic_id', $academicId)
                ->pluck('amount', 'class_id');

            return view('backEnd.academics.classTuition', compact(
                'classes',
                'amounts',
                'years',
                'academicId'
            ));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');

            return redirect()->back();
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'academic_id' => ['required', 'integer'],
            'amount' => ['array'],
            'amount.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $schoolId = auth()->user()->school_id;
            $academicId = (int) $request->academic_id;

            /* Only the year levels this school actually has. The amounts arrive
               keyed by class id from the form, and an id that is not ours has no
               business creating a row. */
            $ownClassIds = SmClass::where('school_id', $schoolId)->pluck('id')->all();

            DB::transaction(function () use ($request, $schoolId, $academicId, $ownClassIds) {
                foreach ((array) $request->input('amount', []) as $classId => $amount) {
                    if (!in_array((int) $classId, $ownClassIds, true)) {
                        continue;
                    }

                    /* An emptied box removes the amount rather than storing zero.
                       Zero is a year level that costs nothing, which is a real if
                       unusual thing to say; blank is "not set", and the screens
                       read the two differently. */
                    if ($amount === null || $amount === '') {
                        ClassTuition::where('school_id', $schoolId)
                            ->where('class_id', (int) $classId)
                            ->where('academic_id', $academicId)
                            ->delete();

                        continue;
                    }

                    ClassTuition::updateOrCreate(
                        [
                            'school_id' => $schoolId,
                            'class_id' => (int) $classId,
                            'academic_id' => $academicId,
                        ],
                        ['amount' => round((float) $amount, 2)]
                    );
                }
            });

            Toastr::success(__('academics.tuition_setup_saved'), 'Success');

            return redirect()->route('class-tuition', ['academic_id' => $academicId]);
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');

            return redirect()->back()->withInput();
        }
    }
}
