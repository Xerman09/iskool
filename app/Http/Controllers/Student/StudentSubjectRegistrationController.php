<?php

namespace App\Http\Controllers\Student;

use App\SmItem;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentBalanceBreakdown;
use App\Traits\SubjectRegistration;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesInvoiceChield;
use Illuminate\Support\Facades\Log;

class StudentSubjectRegistrationController extends Controller
{
    /* The rules of registration live in SubjectRegistration, because the registrar
       enrolls a student by the same rules from AdminSubjectRegistrationController -
       see that trait. What stays here is only what is particular to a student doing
       it themselves: the student is whoever is logged in. */
    use EnrollmentBalanceBreakdown, SubjectRegistration;

    public function index(Request $request)
    {
        try {
            $student = Auth::user()->student;
            $schoolId = Auth::user()->school_id;

            $activeSemester = null;
            $subjects = collect();

            if ($student && $student->course_id && $student->curriculum_version_id) {
                $activeSemester = $this->activeSemesterFor($schoolId);

                if ($activeSemester && $student->class_id) {
                    $subjects = $this->subjectsWithBlocksFor($student, $activeSemester, $schoolId);
                }
            }

            $hasSubmitted = $this->hasSubmittedRegistration($subjects);

            return view('backEnd.studentPanel.subjectRegistration', compact('student', 'activeSemester', 'subjects', 'hasSubmitted'));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function register(Request $request)
    {
        try {
            $student = Auth::user()->student;
            $schoolId = Auth::user()->school_id;

            if (!$student || !$student->course_id || !$student->curriculum_version_id) {
                Toastr::error('You have not been assigned a program yet. Please contact the Registrar.', 'Failed');
                return redirect()->back();
            }

            $activeSemester = $this->activeSemesterFor($schoolId);
            if (!$activeSemester) {
                Toastr::error('No semester is currently open for enrollment.', 'Failed');
                return redirect()->back();
            }

            $result = $this->saveRegistration(
                $student,
                $activeSemester,
                $schoolId,
                (array) $request->input('assign_subject_id', [])
            );

            $message = 'Subjects registered. Your enrollment is pending review by the Registrar/Cashier.';
            if ($result['lockedSubjectNames']) {
                $message .= " Not included (prerequisite not yet passed): {$result['lockedSubjectNames']}.";
            }
            Toastr::success($message, 'Success');
            return redirect()->back();
        } catch (\RuntimeException $e) {
            // Carries the reason the registration was refused - see SubjectRegistration.
            Toastr::error($e->getMessage(), 'Failed');
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function balanceSummary($state = 'view')
    {
        try {
            $student = Auth::user()->student;

            if (!$student || !$student->course_id) {
                Toastr::error('You have not been assigned a program yet.', 'Failed');
                return redirect()->back();
            }

            $breakdown = $this->balanceBreakdownFor($student);

            $record = StudentRecord::where('student_id', $student->id)
                ->where('school_id', $student->school_id)
                ->where('academic_id', getAcademicId())
                ->where('is_promote', 0)
                ->first();

            $itemLines = collect();
            if ($record) {
                $invoiceIds = FmFeesInvoice::where('record_id', $record->id)->pluck('id');
                $itemLines = FmFeesInvoiceChield::whereIn('fees_invoice_id', $invoiceIds)
                    ->whereNotNull('sm_item_id')
                    ->with('item')
                    ->get();
            }

            // Only needed for the interactive view (the buy-items modal) - the print
            // view has no use for it and skips the query entirely.
            $items = collect();
            if ($state !== 'print') {
                $items = SmItem::where('school_id', $student->school_id)
                    ->whereNotNull('unit_price')
                    ->where('total_in_stock', '>', 0)
                    ->with('category')
                    ->get();
            }

            $data = array_merge($breakdown, [
                'student' => $student,
                'itemLines' => $itemLines,
                'itemsTotal' => $breakdown['itemsBilled'],
                'items' => $items,
                'printUrl' => route('student-balance-summary', ['state' => 'print']),
            ]);

            return $state == 'print'
                ? view('backEnd.academics.balanceSummaryPrint', $data)
                : view('backEnd.academics.balanceSummary', $data);
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
