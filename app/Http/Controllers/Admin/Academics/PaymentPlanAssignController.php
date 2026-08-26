<?php

namespace App\Http\Controllers\Admin\Academics;

use App\SmStudent;
use App\PaymentPlanType;
use App\PaymentPlanAssign;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentBalanceBreakdown;
use App\Traits\EnrollmentInvoicing;
use Brian2694\Toastr\Facades\Toastr;

class PaymentPlanAssignController extends Controller
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

            $eligibleStudents = SmStudent::where('school_id', $schoolId)
                ->where('enrollment_status', 'enrolled')
                ->orderBy('first_name')
                ->get();

            $paymentPlanTypes = PaymentPlanType::where('school_id', $schoolId)->orderBy('id')->get();

            $assignedPlans = PaymentPlanAssign::where('school_id', $schoolId)
                ->where('active_status', 1)
                ->with(['planType', 'student', 'invoices.invoiceDetails'])
                ->orderByDesc('id')
                ->get();

            $assignedPlans->each(function ($assign) {
                $assign->paidCount = $assign->invoices->where('payment_status', 'paid')->count();
            });

            return view('backEnd.academics.payment_plan_assign', compact(
                'eligibleStudents',
                'paymentPlanTypes',
                'assignedPlans'
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
                'payment_plan_type_id' => 'required|exists:payment_plan_types,id',
                'first_due_date' => 'required|date',
                'days_between_installments' => 'required|integer|min:1',
            ]);

            $schoolId = auth()->user()->school_id;
            $student = SmStudent::where('school_id', $schoolId)->findOrFail($request->student_id);

            if ($student->enrollment_status !== 'enrolled') {
                Toastr::error('This student must have their down payment paid (enrolled) before a payment plan can be assigned.', 'Failed');
                return redirect()->back();
            }

            $alreadyOnPlan = PaymentPlanAssign::where('school_id', $schoolId)
                ->where('student_id', $student->id)
                ->where('course_id', $student->course_id)
                ->where('active_status', 1)
                ->exists();

            if ($alreadyOnPlan) {
                Toastr::error('This student already has an active payment plan.', 'Failed');
                return redirect()->back();
            }

            $record = StudentRecord::where('school_id', $schoolId)
                ->where('student_id', $student->id)
                ->where('academic_id', getAcademicId())
                ->where('is_promote', 0)
                ->first();

            if (!$record) {
                Toastr::error('No active student record found. Cannot create installment invoices.', 'Failed');
                return redirect()->back();
            }

            $remainingBalance = $this->balanceBreakdownFor($student)['remainingBalance'];

            if ($remainingBalance <= 0) {
                Toastr::error('This student has no remaining balance to split into a payment plan.', 'Failed');
                return redirect()->back();
            }

            $planType = PaymentPlanType::where('school_id', $schoolId)->findOrFail($request->payment_plan_type_id);
            $installments = $planType->number_of_installments;

            $planAssign = new PaymentPlanAssign();
            $planAssign->payment_plan_type_id = $planType->id;
            $planAssign->student_id = $student->id;
            $planAssign->record_id = $record->id;
            $planAssign->course_id = $student->course_id;
            $planAssign->total_amount = $remainingBalance;
            $planAssign->number_of_installments = $installments;
            $planAssign->active_status = 1;
            $planAssign->created_by = auth()->user()->id;
            $planAssign->school_id = $schoolId;
            $planAssign->academic_id = getAcademicId();
            $planAssign->save();

            $this->splitAndCreateInstallments(
                $student,
                $record,
                $planAssign,
                $remainingBalance,
                $installments,
                1,
                \Carbon\Carbon::parse($request->first_due_date)->startOfDay(),
                (int) $request->days_between_installments
            );

            Toastr::success("Payment plan assigned. {$installments} installment invoice(s) created, totaling " . number_format($remainingBalance, 2) . '.', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            $schoolId = auth()->user()->school_id;

            $planAssign = PaymentPlanAssign::where('school_id', $schoolId)
                ->with(['planType', 'student', 'invoices.invoiceDetails'])
                ->findOrFail($id);

            $unpaidInvoices = $planAssign->invoices->where('payment_status', '!=', 'paid')->values();

            if ($unpaidInvoices->isEmpty()) {
                Toastr::error('This payment plan is already fully paid — nothing left to reschedule.', 'Failed');
                return redirect()->route('payment-plan-assign');
            }

            $paymentPlanTypes = PaymentPlanType::where('school_id', $schoolId)->orderBy('id')->get();

            $firstUnpaidDueDate = $unpaidInvoices->first()->due_date;
            $daysBetween = 30;
            if ($unpaidInvoices->count() > 1) {
                $daysBetween = \Carbon\Carbon::parse($unpaidInvoices[0]->due_date)
                    ->diffInDays(\Carbon\Carbon::parse($unpaidInvoices[1]->due_date));
            }

            return view('backEnd.academics.payment_plan_assign_edit', compact(
                'planAssign',
                'paymentPlanTypes',
                'firstUnpaidDueDate',
                'daysBetween'
            ));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:payment_plan_assigns,id',
                'payment_plan_type_id' => 'required|exists:payment_plan_types,id',
                'first_due_date' => 'required|date',
                'days_between_installments' => 'required|integer|min:1',
            ]);

            $schoolId = auth()->user()->school_id;

            $planAssign = PaymentPlanAssign::where('school_id', $schoolId)
                ->with('invoices.invoiceDetails')
                ->findOrFail($request->id);

            $student = SmStudent::where('school_id', $schoolId)->findOrFail($planAssign->student_id);
            $record = StudentRecord::where('school_id', $schoolId)->findOrFail($planAssign->record_id);

            $paidInvoices = $planAssign->invoices->where('payment_status', 'paid');
            $partialInvoices = $planAssign->invoices->where('payment_status', 'partial');
            $unpaidInvoices = $planAssign->invoices->where('payment_status', 'unpaid');

            $partialOutstanding = $partialInvoices->sum(function ($invoice) {
                return $invoice->invoiceDetails->sum('amount') - $invoice->invoiceDetails->sum('paid_amount');
            });
            $amountRemaining = $unpaidInvoices->sum(fn ($invoice) => $invoice->invoiceDetails->sum('amount')) + $partialOutstanding;

            if ($amountRemaining <= 0) {
                Toastr::error('This payment plan is already fully paid — nothing left to reschedule.', 'Failed');
                return redirect()->route('payment-plan-assign');
            }

            $paidCount = $paidInvoices->count() + $partialInvoices->count();

            // Only fully-unpaid installments are replaced; anything with a payment on
            // it (paid OR partial) is left untouched so we never destroy payment history
            // or re-bill money already collected. A partial invoice's outstanding portion
            // is folded into the new schedule's total instead.
            foreach ($unpaidInvoices as $invoice) {
                \Modules\Fees\Entities\FmFeesInvoiceChield::where('fees_invoice_id', $invoice->id)->delete();
                $invoice->delete();
            }

            $planType = PaymentPlanType::where('school_id', $schoolId)->findOrFail($request->payment_plan_type_id);
            $newCount = $planType->number_of_installments;

            $planAssign->payment_plan_type_id = $planType->id;
            $planAssign->number_of_installments = $paidCount + $newCount;
            $planAssign->updated_by = auth()->user()->id;
            $planAssign->save();

            $this->splitAndCreateInstallments(
                $student,
                $record,
                $planAssign,
                $amountRemaining,
                $newCount,
                $paidCount + 1,
                \Carbon\Carbon::parse($request->first_due_date)->startOfDay(),
                (int) $request->days_between_installments
            );

            Toastr::success("Payment plan rescheduled. {$newCount} remaining installment(s) recreated, totaling " . number_format($amountRemaining, 2) . '.', 'Success');
            return redirect()->route('payment-plan-assign');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    private function splitAndCreateInstallments(SmStudent $student, StudentRecord $record, PaymentPlanAssign $planAssign, $amount, $count, $startingInstallmentNo, $firstDueDate, $daysBetween)
    {
        $installmentType = $this->tuitionInstallmentFeesType();
        $perInstallment = floor(($amount / $count) * 100) / 100;
        $runningTotal = 0;

        for ($i = 1; $i <= $count; $i++) {
            $installmentAmount = $i < $count ? $perInstallment : round($amount - $runningTotal, 2);
            $runningTotal += $installmentAmount;

            $dueDate = $firstDueDate->copy()->addDays(($i - 1) * $daysBetween);
            $dueInDays = now()->startOfDay()->diffInDays($dueDate, false);

            $this->createInvoiceLine($student, $record, $installmentType, $installmentAmount, $dueInDays, $planAssign->id, $startingInstallmentNo + $i - 1);
        }
    }
}
