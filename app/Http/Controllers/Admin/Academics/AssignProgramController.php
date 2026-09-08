<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\SmStudent;
use App\SmSubject;
use App\CurriculumVersion;
use App\SmOptionalSubjectAssign;
use App\Models\StudentRecord;
use App\Models\StudentProgramHistory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentBalanceBreakdown;
use App\Traits\EnrollmentInvoicing;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesInvoiceChield;

class AssignProgramController extends Controller
{
    use EnrollmentBalanceBreakdown, EnrollmentInvoicing;

    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $schoolId = auth()->user()->school_id;

            $courses = Course::where('school_id', $schoolId)->get();
            $curriculumVersions = CurriculumVersion::where('school_id', $schoolId)->get();
            $students = SmStudent::where('school_id', $schoolId)->with('course')->orderBy('first_name')->get();

            // So the Program dropdown can hide graduate-level programs for anyone
            // who hasn't actually graduated from an undergraduate one yet - the
            // same rule store() enforces, just surfaced before the click instead
            // of after.
            $graduatedStudentIds = \App\Models\Graduate::whereIn('student_id', $students->pluck('id'))
                ->distinct()
                ->pluck('student_id');

            $students->each(function ($s) use ($graduatedStudentIds) {
                $s->hasGraduated = $graduatedStudentIds->contains($s->id)
                    && optional($s->course)->level === 'undergraduate';
            });

            $assignedStudents = SmStudent::where('school_id', $schoolId)
                ->whereNotNull('course_id')
                ->with(['course', 'curriculumVersion', 'class', 'academicYear'])
                ->orderBy('first_name')
                ->get();

            $registeredStudentIds = SmOptionalSubjectAssign::where('school_id', $schoolId)
                ->where('academic_id', getAcademicId())
                ->whereIn('student_id', $assignedStudents->pluck('id'))
                ->distinct()
                ->pluck('student_id');

            $assignedStudents->each(function ($s) use ($registeredStudentIds) {
                $s->hasRegisteredSubjects = $registeredStudentIds->contains($s->id);
                $s->remainingBalance = null;
                $s->paymentPlan = null;
                $s->priorYearBalance = $this->priorYearBalanceFor($s);

                if ($s->hasRegisteredSubjects && $s->class_id) {
                    $breakdown = $this->balanceBreakdownFor($s);
                    $s->remainingBalance = $breakdown['remainingBalance'];
                    $s->paymentPlan = $breakdown['paymentPlan'];
                }
            });

            $selectedStudentId = $request->student_id;

            return view('backEnd.academics.assign_program', compact(
                'courses',
                'curriculumVersions',
                'students',
                'assignedStudents',
                'selectedStudentId'
            ));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|exists:sm_students,id',
                'course_id' => 'required|exists:courses,id',
                'curriculum_version_id' => 'required|exists:curriculum_versions,id',
            ]);

            $student = SmStudent::where('school_id', auth()->user()->school_id)->findOrFail($request->student_id);

            $previousCourseId = $student->course_id;
            $previousCurriculumVersionId = $student->curriculum_version_id;

            $newCourse = Course::where('school_id', auth()->user()->school_id)->find($request->course_id);

            if ($newCourse && $newCourse->level === 'graduate') {
                $previousCourse = Course::where('school_id', auth()->user()->school_id)->find($previousCourseId);
                $hasGraduated = \App\Models\Graduate::where('student_id', $student->id)->exists();

                if (!$hasGraduated || !$previousCourse || $previousCourse->level !== 'undergraduate') {
                    Toastr::error(__('academics.must_graduate_undergrad_first'), 'Failed');
                    return redirect()->back();
                }
            }

            $student->course_id = $request->course_id;
            $student->curriculum_version_id = $request->curriculum_version_id;
            if (!$student->class_id) {
                $student->class_id = SmSubject::where('course_id', $request->course_id)
                    ->where('curriculum_version_id', $request->curriculum_version_id)
                    ->min('class_id');
            }

            $isShift = $previousCourseId && $previousCourseId != $student->course_id;

            // A shift into a different program (including a graduate program after
            // finishing undergrad) starts a new enrollment from scratch - the
            // downpayment/invoicing gate shouldn't be skipped just because the
            // student was already 'enrolled' in their previous program.
            if ($isShift || $student->enrollment_status === null) {
                $student->enrollment_status = 'pending';
            }
            $student->save();

            if ($previousCourseId != $student->course_id || $previousCurriculumVersionId != $student->curriculum_version_id) {
                StudentProgramHistory::create([
                    'student_id' => $student->id,
                    'previous_course_id' => $previousCourseId,
                    'current_course_id' => $student->course_id,
                    'previous_curriculum_version_id' => $previousCurriculumVersionId,
                    'current_curriculum_version_id' => $student->curriculum_version_id,
                    'changed_by' => Auth::id(),
                    'school_id' => auth()->user()->school_id,
                    'academic_id' => getAcademicId(),
                ]);
            }

            Toastr::success($isShift
                ? 'Program shifted. The student can now register for subjects once the semester is open for enrollment.'
                : 'Program assigned. The student can now register for subjects once the semester is open for enrollment.', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function generateInvoice(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|exists:sm_students,id',
            ]);

            $student = SmStudent::where('school_id', auth()->user()->school_id)->findOrFail($request->student_id);

            $hasRegisteredSubjects = SmOptionalSubjectAssign::where('school_id', auth()->user()->school_id)
                ->where('academic_id', getAcademicId())
                ->where('student_id', $student->id)
                ->exists();

            if (!$hasRegisteredSubjects) {
                Toastr::error('This student has not finished subject registration yet.', 'Failed');
                return redirect()->back();
            }

            $record = StudentRecord::where('school_id', auth()->user()->school_id)
                ->where('student_id', $student->id)
                ->where('academic_id', getAcademicId())
                ->where('is_promote', 0)
                ->first();

            if ($record && !$record->class_id) {
                $record->class_id = $student->class_id;
                $record->save();
            }

            if (!$record) {
                Toastr::error('No active student record found. Cannot create invoice.', 'Failed');
                return redirect()->back();
            }

            $tuitionType = $this->tuitionFeesType();

            $existingInvoice = FmFeesInvoice::where('school_id', auth()->user()->school_id)
                ->where('record_id', $record->id)
                ->whereHas('invoiceDetails', fn ($q) => $q->where('fees_type', $tuitionType->id))
                ->first();

            if ($existingInvoice) {
                Toastr::success('This student already has an enrollment invoice for this program. Showing it below.', 'Success');
                return redirect()->route('fees.fees-invoice-view', ['id' => $existingInvoice->id, 'state' => 'view']);
            }

            $breakdown = $this->balanceBreakdownFor($student);

            // One invoice for the whole order, itemized (tuition + each misc fee) for
            // the full amount - not capped to the down payment. The down payment is
            // just the threshold that has to be paid on THIS invoice for the student
            // to be considered enrolled (see FeesExtendedController::
            // markStudentEnrolledIfPending()), not a billed line of its own.
            //
            // If the student already bought something from the item store before this
            // invoice was ever generated (order of operations shouldn't matter), reuse
            // that still-open invoice instead of starting a separate one, and promote
            // it to 'fees' now that it carries the enrollment gate.
            $invoice = $this->openOrderInvoiceFor($record) ?: $this->newOrderInvoice($student, $record, 'fees', 3);
            $invoice->type = 'fees';
            $invoice->save();

            foreach ($breakdown['lines'] as $line) {
                $feesType = $line['feesType'] ?? $tuitionType;
                $this->addChieldLine($invoice, $feesType, $line['amount']);
            }

            Toastr::success('Enrollment invoice created for ' . number_format($breakdown['totalAmount'], 2) . '. Collect the down payment of ' . number_format($breakdown['downPayment'], 2) . ' below to enroll this student now, or assign a payment plan instead - either one enrolls the student.', 'Success');

            return redirect()->route('fees.fees-invoice-view', ['id' => $invoice->id, 'state' => 'view']);
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function balanceSummary($studentId, $state = 'view')
    {
        try {
            $student = SmStudent::where('school_id', auth()->user()->school_id)->findOrFail($studentId);
            $breakdown = $this->balanceBreakdownFor($student);

            $record = StudentRecord::where('school_id', auth()->user()->school_id)
                ->where('student_id', $student->id)
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

            $data = array_merge($breakdown, [
                'student' => $student,
                'itemLines' => $itemLines,
                'itemsTotal' => $breakdown['itemsBilled'],
                'printUrl' => route('assign-program-balance-summary', ['student' => $student->id, 'state' => 'print']),
            ]);

            return $state == 'print'
                ? view('backEnd.academics.balanceSummaryPrint', $data)
                : view('backEnd.academics.balanceSummary', $data);
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function ledger($studentId)
    {
        try {
            $student = SmStudent::where('school_id', auth()->user()->school_id)->findOrFail($studentId);
            $ledger = $this->ledgerFor($student);

            return view('backEnd.academics.transactionLedger', array_merge($ledger, [
                'student' => $student,
            ]));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function updateProgramStatus(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|integer|exists:sm_students,id',
            ]);

            $student = SmStudent::where('school_id', auth()->user()->school_id)
                ->whereNotNull('course_id')
                ->find($request->student_id);
            if (!$student) {
                return response()->json(['error' => 'Operation Failed']);
            }

            $student->program_status = $request->program_status ? 1 : 0;
            if (!$student->program_status) {
                $student->alumni_active_status = 1;
            }
            $student->save();

            return response()->json(['message' => 'Operation Success']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Operation Failed']);
        }
    }

    public function updateAlumniStatus(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|integer|exists:sm_students,id',
            ]);

            $student = SmStudent::where('school_id', auth()->user()->school_id)
                ->whereNotNull('course_id')
                ->where('program_status', 1)
                ->find($request->student_id);
            if (!$student) {
                return response()->json(['error' => 'Operation Failed']);
            }

            $student->alumni_active_status = $request->alumni_active_status ? 1 : 0;
            $student->save();

            return response()->json(['message' => 'Operation Success']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Operation Failed']);
        }
    }

}
