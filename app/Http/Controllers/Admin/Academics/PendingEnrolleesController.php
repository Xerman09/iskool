<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\SmOptionalSubjectAssign;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentBalanceBreakdown;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesInvoice;
use Illuminate\Support\Facades\Log;

/**
 * The students whose invoice is the next thing standing between them and being
 * enrolled: registration done, nothing billed yet.
 *
 * 'pending' on its own does not say that. It is one status covering three
 * different jobs - register the subjects, raise the invoice, take the money -
 * and only the middle one belongs here. Reception used to reach it through
 * Assign Program, reading down a table of every student the school has ever
 * admitted and working out for each whether an invoice existed. This queue is
 * that reading, already done: everyone on it can be invoiced right now, and the
 * button does it.
 *
 * Read-only by design. Raising the invoice stays with
 * AssignProgramController@generateInvoice, which the button posts to, so there
 * is one implementation of what an enrolment invoice is and this screen cannot
 * drift from it.
 */
class PendingEnrolleesController extends Controller
{
    use EnrollmentBalanceBreakdown;

    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $schoolId = auth()->user()->school_id;

            $courses = Course::selectable()->where('school_id', $schoolId)->get();

            /* This year's records only, and only the ones not yet promoted - a
               record from a year that has ended keeps the standing it closed on
               and is nobody's work today. */
            $records = StudentRecord::where('school_id', $schoolId)
                ->where('academic_id', getAcademicId())
                ->where('is_promote', 0)
                ->where('enrollment_status', 'pending')
                ->with(['studentDetail.course', 'class'])
                ->get()
                /* studentDetail() is a withDefault() relation, so a record whose
                   student row is gone hands back a blank model rather than null.
                   Guarding on ->id is what actually excludes those; a null check
                   would pass every one of them straight through. */
                ->filter(fn ($record) => optional($record->studentDetail)->id);

            if ($request->filled('course_id')) {
                $records = $records->filter(
                    fn ($record) => $record->studentDetail->course_id == $request->course_id
                );
            }

            /* Two lookups for the whole queue rather than two per row. Registered
               subjects are what generateInvoice() refuses without, and an existing
               enrolment invoice is what means the billing is already done - between
               them they decide who belongs on this screen at all. */
            $subjectCounts = SmOptionalSubjectAssign::where('school_id', $schoolId)
                ->where('academic_id', getAcademicId())
                ->whereIn('student_id', $records->pluck('student_id'))
                ->get(['student_id'])
                ->countBy('student_id');

            // Keyed by record, which is what ties an invoice to a year (see
            // newOrderInvoice()).
            $invoicedRecordIds = FmFeesInvoice::where('school_id', $schoolId)
                ->where('type', 'fees')
                ->whereIn('record_id', $records->pluck('id'))
                ->pluck('record_id');

            // SmStudent carries semester_id but has no semester relation, so the
            // names are looked up once here instead of per row.
            $semesters = \App\Semester::where('school_id', $schoolId)->get()->keyBy('id');

            /* Already billed: their invoice exists, so the money is the cashier's
               business now, not this queue's. */
            $records = $records->reject(fn ($record) => $invoicedRecordIds->contains($record->id));

            /* Registered nothing yet, so there is no load to price and
               generateInvoice() would refuse. They are not this screen's work, but
               neither is anybody else's - nothing in the system lists them - so the
               count is carried through and shown above the table rather than
               dropped silently. */
            $awaitingRegistration = $records->filter(
                fn ($record) => $subjectCounts->get($record->student_id, 0) === 0
            );

            $rows = $records
                ->filter(fn ($record) => $subjectCounts->get($record->student_id, 0) > 0)
                ->map(function ($record) use ($subjectCounts, $semesters) {
                    $student = $record->studentDetail;

                    /* What generating will bill, and what has to land before the
                       record flips to 'enrolled' (see FeesExtendedController). Shown
                       before the click, so reception is not generating blind. A
                       student with no year level set prices nothing, so the breakdown
                       is skipped rather than asked for. */
                    $breakdown = $student->class_id ? $this->balanceBreakdownFor($student) : null;

                    return [
                        'record' => $record,
                        'student' => $student,
                        'semester' => optional($semesters->get($student->semester_id))->semester_name,
                        'subjectCount' => $subjectCounts->get($student->id, 0),
                        'totalAmount' => $breakdown['totalAmount'] ?? null,
                        'downPayment' => $breakdown['downPayment'] ?? null,
                    ];
                })
                // So the same student is always in the same place on the screen.
                ->sortBy(fn ($row) => $row['student']->full_name)
                ->values();

            $awaitingRegistrationCount = $awaitingRegistration->count();

            return view('backEnd.academics.pendingEnrollees', compact(
                'rows',
                'courses',
                'awaitingRegistrationCount'
            ));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
