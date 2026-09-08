<?php

namespace App\Http\Controllers\Admin\Academics;

use App\SmStudent;
use App\PaymentPlanType;
use App\PaymentPlanAssign;
use App\PaymentPlanInstallment;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentBalanceBreakdown;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesInvoice;

class PaymentPlanAssignController extends Controller
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

            $eligibleStudents = SmStudent::where('school_id', $schoolId)
                ->where('enrollment_status', 'enrolled')
                ->orderBy('first_name')
                ->get();

            $paymentPlanTypes = PaymentPlanType::where('school_id', $schoolId)->orderBy('id')->get();

            $assignedPlans = PaymentPlanAssign::where('school_id', $schoolId)
                ->where('active_status', 1)
                ->with(['planType', 'student', 'installments', 'invoice'])
                ->orderByDesc('id')
                ->get();

            $assignedPlans->each(function ($assign) {
                $assign->schedule = $this->installmentSchedule($assign);
                $assign->paidCount = $assign->schedule->where('status', 'paid')->count();
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
                'invoice_id' => 'required_without:student_id|nullable|exists:fm_fees_invoices,id',
                'student_id' => 'required_without:invoice_id|nullable|exists:sm_students,id',
                'payment_plan_type_id' => 'required|exists:payment_plan_types,id',
                'first_due_date' => 'required|date',
                'days_between_installments' => 'required|integer|min:1',
            ]);

            $schoolId = auth()->user()->school_id;

            if ($request->filled('invoice_id')) {
                // Assigned directly from that invoice's own page (e.g. by reception) -
                // works for any invoice, enrollment or item-store alike, not just tuition.
                $invoice = FmFeesInvoice::where('school_id', $schoolId)->findOrFail($request->invoice_id);
                $student = SmStudent::where('school_id', $schoolId)->findOrFail($invoice->student_id);
                $context = $invoice->type === 'store' ? 'store' : 'tuition';

                // A plan may now be assigned to a still-'pending' student's enrollment
                // invoice too - that's the whole point of the "payment plan instead of
                // a down payment" path. There's nothing to fold/protect here: the plan
                // splits whatever the invoice's live due amount is (see remainingBalance
                // below), whether that's the full total (no down payment collected yet)
                // or what's left after a partial down payment. Enrollment itself then
                // follows from any payment landing past this plan's baseline - see
                // FeesExtendedController::markStudentEnrolledIfPending().
            } else {
                $student = SmStudent::where('school_id', $schoolId)->findOrFail($request->student_id);

                $context = $request->context ?: 'tuition';

                $record = StudentRecord::where('school_id', $schoolId)
                    ->where('student_id', $student->id)
                    ->where('academic_id', getAcademicId())
                    ->where('is_promote', 0)
                    ->first();

                if (!$record) {
                    Toastr::error('No active student record found. Cannot assign a payment plan.', 'Failed');
                    return redirect()->back();
                }

                $invoice = FmFeesInvoice::where('school_id', $schoolId)
                    ->where('record_id', $record->id)
                    ->where('type', 'fees')
                    ->first();

                if (!$invoice) {
                    Toastr::error('Generate the enrollment invoice first.', 'Failed');
                    return redirect()->back();
                }
            }

            // Uniqueness is per invoice, not per student+context - one invoice, one
            // active plan at a time, regardless of which screen created it.
            $alreadyOnPlan = PaymentPlanAssign::where('fm_fees_invoice_id', $invoice->id)
                ->where('active_status', 1)
                ->exists();

            if ($alreadyOnPlan) {
                Toastr::error('This invoice already has an active payment plan.', 'Failed');
                return redirect()->back();
            }

            // Installments are partial payments against the invoice's existing lines,
            // not new invoices. Those lines are already fully billed (tuition/misc, or
            // items), so the invoice's own live due amount IS the balance to split - no
            // separate "installment" line needs to be added.
            $remainingBalance = round((float) $invoice->Tamount - (float) $invoice->Tpaidamount, 2);

            if ($remainingBalance <= 0) {
                Toastr::error('This invoice has no remaining balance to split into a payment plan.', 'Failed');
                return redirect()->back();
            }

            $planType = PaymentPlanType::where('school_id', $schoolId)->findOrFail($request->payment_plan_type_id);
            $installments = $planType->number_of_installments;

            $planAssign = new PaymentPlanAssign();
            $planAssign->payment_plan_type_id = $planType->id;
            $planAssign->context = $context;
            $planAssign->fm_fees_invoice_id = $invoice->id;
            $planAssign->student_id = $student->id;
            $planAssign->record_id = $invoice->record_id;
            $planAssign->course_id = $student->course_id;
            $planAssign->total_amount = $remainingBalance;
            $planAssign->baseline_paid_amount = (float) $invoice->Tpaidamount;
            $planAssign->number_of_installments = $installments;
            $planAssign->active_status = 1;
            $planAssign->created_by = auth()->user()->id;
            $planAssign->school_id = $schoolId;
            $planAssign->academic_id = getAcademicId();
            $planAssign->save();

            $this->buildInstallmentSchedule(
                $planAssign,
                $remainingBalance,
                $installments,
                1,
                \Carbon\Carbon::parse($request->first_due_date)->startOfDay(),
                (int) $request->days_between_installments
            );

            Toastr::success("Payment plan assigned. {$installments} installment(s) scheduled, totaling " . number_format($remainingBalance, 2) . '.', 'Success');

            return $request->filled('invoice_id')
                ? redirect()->route('fees.fees-invoice-view', ['id' => $invoice->id, 'state' => 'view'])
                : redirect()->back();
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
                ->with(['planType', 'student', 'installments', 'invoice'])
                ->findOrFail($id);

            $schedule = $this->installmentSchedule($planAssign);
            $unpaid = $schedule->where('status', '!=', 'paid')->values();

            if ($unpaid->isEmpty()) {
                Toastr::error('This payment plan is already fully paid — nothing left to reschedule.', 'Failed');
                return redirect()->route('payment-plan-assign');
            }

            $paymentPlanTypes = PaymentPlanType::where('school_id', $schoolId)->orderBy('id')->get();

            $firstUnpaidDueDate = $unpaid->first()['due_date'];
            $daysBetween = 30;
            if ($unpaid->count() > 1) {
                $daysBetween = \Carbon\Carbon::parse($unpaid[0]['due_date'])
                    ->diffInDays(\Carbon\Carbon::parse($unpaid[1]['due_date']));
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
                ->with(['installments', 'invoice'])
                ->findOrFail($request->id);

            $schedule = $this->installmentSchedule($planAssign);

            $amountRemaining = $planAssign->invoice
                ? round((float) $planAssign->invoice->Tamount - (float) $planAssign->invoice->Tpaidamount, 2)
                : 0;

            if ($amountRemaining <= 0) {
                Toastr::error('This payment plan is already fully paid — nothing left to reschedule.', 'Failed');
                return redirect()->route('payment-plan-assign');
            }

            $paidInstallments = $schedule->where('status', 'paid');
            $paidCount = $paidInstallments->count();

            // Only not-yet-fully-paid schedule rows are replaced; a "paid" row is left
            // untouched. Real payment history lives on the invoice's own lines, not on
            // these schedule rows, so replacing a "partial" row loses nothing -
            // amountRemaining above already reflects the true live due amount.
            $toReplace = $schedule->where('status', '!=', 'paid')->pluck('id');
            PaymentPlanInstallment::whereIn('id', $toReplace)->delete();

            $planType = PaymentPlanType::where('school_id', $schoolId)->findOrFail($request->payment_plan_type_id);
            $newCount = $planType->number_of_installments;

            $planAssign->payment_plan_type_id = $planType->id;
            $planAssign->number_of_installments = $paidCount + $newCount;
            // Whatever's already settled (kept installments) plus what's being
            // redistributed now - not "Tamount minus baseline_paid_amount", since a
            // plan created before its down payment was actually paid (baseline still
            // 0) would otherwise keep reporting the invoice's full original total
            // forever, even after rescheduling around the true remaining balance.
            $planAssign->total_amount = round($paidInstallments->sum('amount') + $amountRemaining, 2);
            $planAssign->updated_by = auth()->user()->id;
            $planAssign->save();

            $this->buildInstallmentSchedule(
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

    private function buildInstallmentSchedule(PaymentPlanAssign $planAssign, $amount, $count, $startingInstallmentNo, $firstDueDate, $daysBetween)
    {
        $perInstallment = floor(($amount / $count) * 100) / 100;
        $runningTotal = 0;

        for ($i = 1; $i <= $count; $i++) {
            $installmentAmount = $i < $count ? $perInstallment : round($amount - $runningTotal, 2);
            $runningTotal += $installmentAmount;

            $dueDate = $firstDueDate->copy()->addDays(($i - 1) * $daysBetween);

            $installment = new PaymentPlanInstallment();
            $installment->payment_plan_assign_id = $planAssign->id;
            $installment->installment_no = $startingInstallmentNo + $i - 1;
            $installment->due_date = $dueDate->toDateString();
            $installment->amount = $installmentAmount;
            $installment->school_id = $planAssign->school_id;
            $installment->academic_id = $planAssign->academic_id;
            $installment->save();
        }
    }
}
