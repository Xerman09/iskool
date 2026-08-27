<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\SmStudent;
use App\SmSubject;
use App\CurriculumVersion;
use App\SmOptionalSubjectAssign;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentBalanceBreakdown;
use App\Traits\EnrollmentInvoicing;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesInvoice;

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
            $students = SmStudent::where('school_id', $schoolId)->orderBy('first_name')->get();

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

                if ($s->hasRegisteredSubjects && $s->class_id) {
                    $breakdown = $this->balanceBreakdownFor($s);
                    $s->remainingBalance = $breakdown['remainingBalance'];
                    $s->paymentPlan = $breakdown['paymentPlan'];
                }
            });

            return view('backEnd.academics.assign_program', compact(
                'courses',
                'curriculumVersions',
                'students',
                'assignedStudents'
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
            $student->course_id = $request->course_id;
            $student->curriculum_version_id = $request->curriculum_version_id;
            if (!$student->class_id) {
                $student->class_id = SmSubject::where('course_id', $request->course_id)
                    ->where('curriculum_version_id', $request->curriculum_version_id)
                    ->min('class_id');
            }
            if ($student->enrollment_status === null) {
                $student->enrollment_status = 'pending';
            }
            $student->save();

            Toastr::success('Program assigned. The student can now register for subjects once the semester is open for enrollment.', 'Success');
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

            $breakdown = $this->balanceBreakdownFor($student);
            $downPaymentAmount = $breakdown['downPayment'];
            $remainingBalance = $breakdown['remainingBalance'];

            $downPaymentType = $this->downPaymentFeesType();

            $downPaymentInvoice = FmFeesInvoice::where('school_id', auth()->user()->school_id)
                ->where('record_id', $record->id)
                ->where('course_id', $student->course_id)
                ->whereHas('invoiceDetails', fn ($q) => $q->where('fees_type', $downPaymentType->id))
                ->first();

            $alreadyHadDownPayment = (bool) $downPaymentInvoice;
            $downPaymentInvoice = $downPaymentInvoice ?: $this->createInvoiceLine($student, $record, $downPaymentType, $downPaymentAmount, 3);

            // enrollment_status flips to 'enrolled' only once this invoice is actually paid in full —
            // see FeesExtendedController::markStudentEnrolledIfPending(), hooked into the payment-recording flow.

            Toastr::success($alreadyHadDownPayment
                ? 'This student already has a pending down payment invoice for this program. Showing it below.'
                : ('Down payment invoice created for ' . number_format($downPaymentAmount, 2) . '. Remaining balance of ' . number_format($remainingBalance, 2) . ' will be arranged separately by the Registrar once the down payment is settled. The student will be marked enrolled once this invoice is paid.'), 'Success');

            return redirect()->route('fees.fees-invoice-view', ['id' => $downPaymentInvoice->id, 'state' => 'view']);
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
            $data = array_merge($breakdown, [
                'student' => $student,
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
