<?php

namespace App\Traits;

use App\Course;
use App\SmStudent;
use App\SmSubject;
use App\PaymentPlanAssign;
use App\Models\StudentRecord;
use App\Scopes\AcademicSchoolScope;
use Modules\Fees\Entities\FmFeesType;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesTransaction;
use Modules\Fees\Entities\FmFeesInvoiceChield;

trait EnrollmentBalanceBreakdown
{
    use EnrollmentInvoicing;

    protected function balanceBreakdownFor(SmStudent $student)
    {
        $course = Course::where('school_id', $student->school_id)->find($student->course_id);
        $pricePerUnit = optional($course)->price_per_unit ?: 0;

        $semesterIds = SmSubject::where('course_id', $student->course_id)
            ->where('class_id', $student->class_id)
            ->distinct()
            ->orderBy('semester_id')
            ->pluck('semester_id');

        $allLines = collect();
        $firstSemLines = collect();
        $firstSemTotal = null;
        $total = 0;

        foreach ($semesterIds as $index => $semesterId) {
            $semester = \App\Semester::find($semesterId);

            $units = SmSubject::where('course_id', $student->course_id)
                ->where('class_id', $student->class_id)
                ->where('semester_id', $semesterId)
                ->sum('units');
            $unitsPrice = $units * $pricePerUnit;

            // 'feesType' is null for the tuition line (billing resolves it lazily via
            // tuitionFeesType(), which finds-or-creates - we don't want a plain read
            // like this to have the side effect of creating that fee type row).
            $semesterLines = collect([[
                'label' => trim('Tuition' . ($semester ? ' — ' . $semester->semester_name : '') . " ({$units} units)"),
                'amount' => $unitsPrice,
                'feesType' => null,
            ]]);

            $miscFees = FmFeesType::where('school_id', $student->school_id)
                ->where('course_id', $student->course_id)
                ->where('class_id', $student->class_id)
                ->where('semester_id', $semesterId)
                ->get();

            foreach ($miscFees as $fee) {
                $semesterLines->push(['label' => $fee->name, 'amount' => (float) $fee->amount, 'feesType' => $fee]);
            }

            $semesterTotal = $unitsPrice + $miscFees->sum('amount');

            if ($index === 0) {
                $firstSemLines = $semesterLines;
                $firstSemTotal = $semesterTotal;
            }

            $allLines = $allLines->concat($semesterLines->all());
            $total += $semesterTotal;
        }

        $isPerYear = $student->payment_plan == 'per_year';
        $tuitionMiscTotal = $isPerYear ? $total : ($firstSemTotal ?: 0);
        $lines = $isPerYear ? $allLines : $firstSemLines;

        $standardDownPayment = optional(generalSetting())->down_payment_amount;
        $downPayment = $standardDownPayment ? min($standardDownPayment, $tuitionMiscTotal) : $tuitionMiscTotal;

        $invoiceIds = FmFeesInvoice::where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('course_id', $student->course_id)
            ->pluck('id');

        $itemsBilled = (float) FmFeesInvoiceChield::where('school_id', $student->school_id)
            ->whereIn('fees_invoice_id', $invoiceIds)
            ->whereNotNull('sm_item_id')
            ->sum('amount');

        $itemsPaid = (float) FmFeesInvoiceChield::where('school_id', $student->school_id)
            ->whereIn('fees_invoice_id', $invoiceIds)
            ->whereNotNull('sm_item_id')
            ->sum('paid_amount');

        $itemsDue = max(0, $itemsBilled - $itemsPaid);

        // "Everything added upon enrollment": the tuition/misc projection plus whatever
        // items have actually been billed onto this order's invoice(s).
        $totalAmount = $tuitionMiscTotal + $itemsBilled;

        // Only actually paid amounts reduce the balance — an unpaid line does not
        // lower what the student still owes. Covers every chield across every
        // invoice for this course (tuition/misc, items, whatever's been billed).
        $amountPaid = $this->totalAmountPaidFor($student, $invoiceIds);
        $remainingBalance = max(0, $totalAmount - $amountPaid);

        // Items are always billed/paid on their own chield line, independent of the
        // tuition payment plan - so what actually gets split into installments is the
        // tuition/misc balance only, never items (otherwise an unpaid item would get
        // paid down twice: once via the installment schedule, once via its own line).
        $tuitionRemainingBalance = max(0, $tuitionMiscTotal - ($amountPaid - $itemsPaid));

        $paymentPlan = $this->activePaymentPlanFor($student);

        if ($paymentPlan) {
            $dueNow = $itemsDue + $this->nextInstallmentRemaining($paymentPlan['schedule'], $paymentPlan['paidSoFar']);
        } else {
            // Nothing paid toward tuition/misc yet reduces the down payment owed -
            // this is the same "paid toward the enrollment invoice" figure used above
            // for tuitionRemainingBalance, since there's no separate "Down Payment"
            // line anymore - the enrollment invoice's real tuition/misc lines are it.
            $downPaymentPaid = min($downPayment, $amountPaid - $itemsPaid);
            $dueNow = max(0, $downPayment - $downPaymentPaid) + $itemsDue;
        }

        return [
            'course' => $course,
            'lines' => $lines,
            'totalAmount' => $totalAmount,
            'downPayment' => $downPayment,
            'amountPaid' => $amountPaid,
            'remainingBalance' => $remainingBalance,
            'tuitionRemainingBalance' => $tuitionRemainingBalance,
            'paymentPlan' => $paymentPlan,
            'itemsBilled' => $itemsBilled,
            'itemsDue' => $itemsDue,
            'dueNow' => $dueNow,
        ];
    }

    /**
     * How much more is owed to fully cover the schedule's currently-"next"
     * installment - installment.amount minus whatever partial has already landed
     * on it (prior installments are, by definition of "next", already covered).
     */
    protected function nextInstallmentRemaining($schedule, $paidSoFar)
    {
        $next = $schedule->firstWhere('is_next', true);

        if (!$next) {
            return 0;
        }

        $cumulativeThroughNext = $schedule
            ->where('installment_no', '<=', $next['installment_no'])
            ->sum('amount');

        return max(0, $cumulativeThroughNext - $paidSoFar);
    }

    /**
     * Default suggested payment for an invoice with no payment plan yet. For a
     * not-yet-enrolled student's own enrollment invoice, that's the remaining down
     * payment (the actual threshold that flips them to "enrolled" - see
     * FeesExtendedController::markStudentEnrolledIfPending()), not the invoice's
     * full balance - a student paying a down payment shouldn't be nudged into
     * paying the whole semester up front. Everything else (store invoices, an
     * enrollment invoice for an already-enrolled student topping it up) still
     * defaults to "pay it all".
     */
    protected function suggestedAmountForInvoice(FmFeesInvoice $invoice, $invoiceDetails)
    {
        $totalDue = (float) $invoiceDetails->sum('due_amount');

        if ($invoice->type !== 'fees') {
            return $totalDue;
        }

        $student = SmStudent::find($invoice->student_id);
        if (!$student || $student->enrollment_status === 'enrolled') {
            return $totalDue;
        }

        $downPayment = $this->balanceBreakdownFor($student)['downPayment'];
        $downPaymentRemaining = max(0, $downPayment - (float) $invoice->Tpaidamount);

        return min($downPaymentRemaining, $totalDue);
    }

    /**
     * The active plan (if any) on a specific invoice, plus how much is due this
     * cycle - used by the generic Fees payment-collection screen, which only has
     * an invoice id to work with, not a student's full balance breakdown.
     */
    protected function activePlanForInvoice($invoiceId)
    {
        $assign = PaymentPlanAssign::where('fm_fees_invoice_id', $invoiceId)
            ->where('active_status', 1)
            ->with('planType', 'installments', 'invoice')
            ->first();

        if (!$assign) {
            return null;
        }

        $schedule = $this->installmentSchedule($assign);
        $paidSoFar = $this->installmentPaidSoFar($assign);

        return [
            'assign' => $assign,
            'schedule' => $schedule,
            'dueThisCycle' => $this->nextInstallmentRemaining($schedule, $paidSoFar),
        ];
    }

    /**
     * Spread an amount (e.g. this cycle's installment) across an invoice's lines,
     * oldest line first, capped at each line's own due_amount - a starting
     * suggestion for the payment-collection screen, not a hard split; the cashier
     * can still edit any line or pay more/less than what's pre-filled.
     */
    protected function allocateAcrossLines($invoiceDetails, $amount)
    {
        $remaining = round((float) $amount, 2);
        $allocation = [];

        foreach ($invoiceDetails->sortBy('id') as $detail) {
            $due = max(0, (float) $detail->due_amount);
            $take = $remaining > 0 ? min($remaining, $due) : 0;
            $allocation[$detail->id] = round($take, 2);
            $remaining = round($remaining - $take, 2);
        }

        return $allocation;
    }

    private function activePaymentPlanFor(SmStudent $student)
    {
        $assign = PaymentPlanAssign::where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('course_id', $student->course_id)
            ->where('context', 'tuition')
            ->where('active_status', 1)
            ->with('planType', 'installments', 'invoice')
            ->latest()
            ->first();

        if (!$assign) {
            return null;
        }

        $schedule = $this->installmentSchedule($assign);

        return [
            'name' => optional($assign->planType)->name,
            'total' => $assign->installments->count(),
            'paid' => $schedule->where('status', 'paid')->count(),
            'schedule' => $schedule,
            'paidSoFar' => $this->installmentPaidSoFar($assign),
        ];
    }

    /**
     * How much has landed on the plan's invoice since the plan was created - the
     * invoice's real lines are already fully billed (tuition/misc, or items) before
     * any plan exists, so we don't add a synthetic "installment" line to track
     * against. Instead we snapshot what was already paid at plan-creation time
     * (baseline_paid_amount) and everything paid past that baseline is progress
     * against the schedule, however the payment happens to be split across lines.
     */
    private function installmentPaidSoFar(PaymentPlanAssign $assign)
    {
        if (!$assign->invoice) {
            return 0;
        }

        return max(0, (float) $assign->invoice->Tpaidamount - (float) $assign->baseline_paid_amount);
    }

    /**
     * Waterfall a plan's due-date schedule against paidSoFar (see
     * installmentPaidSoFar()) - installment N is "paid" once cumulative payments
     * reach the sum of installments 1..N, "partial" if that cumulative amount only
     * partly covers it, "unpaid" otherwise.
     */
    protected function installmentSchedule(PaymentPlanAssign $assign)
    {
        $paidSoFar = $this->installmentPaidSoFar($assign);

        $runningTotal = 0;
        $nextFound = false;

        return $assign->installments->values()->map(function ($installment) use (&$runningTotal, &$nextFound, $paidSoFar) {
            $before = $runningTotal;
            $runningTotal = round($runningTotal + (float) $installment->amount, 2);

            if ($paidSoFar >= $runningTotal - 0.01) {
                $status = 'paid';
            } elseif ($paidSoFar > $before) {
                $status = 'partial';
            } else {
                $status = 'unpaid';
            }

            $isNext = false;
            if (!$nextFound && $status !== 'paid') {
                $isNext = true;
                $nextFound = true;
            }

            // How much of paidSoFar actually landed inside this installment's own
            // bucket (before, runningTotal] - lets a partial installment show what's
            // still actually owed for it, not just its flat scheduled amount.
            $appliedToThis = max(0, min($paidSoFar, $runningTotal) - $before);
            $remaining = round((float) $installment->amount - $appliedToThis, 2);

            return [
                'id' => $installment->id,
                'installment_no' => $installment->installment_no,
                'amount' => (float) $installment->amount,
                'remaining' => $remaining,
                'due_date' => $installment->due_date,
                'status' => $status,
                'is_next' => $isNext,
            ];
        });
    }

    /**
     * Everything billed/paid/still due across ALL of a student's current-academic-
     * year invoices (the enrollment invoice plus any mid-year store invoices),
     * plus every individual payment/receipt across them, chronologically - the
     * combined statement the per-invoice pages don't show.
     */
    protected function ledgerFor(SmStudent $student)
    {
        $invoices = FmFeesInvoice::where('student_id', $student->id)
            ->where('school_id', $student->school_id)
            ->with('invoiceDetails.feesType', 'invoiceDetails.item')
            ->get();

        $invoiceIds = $invoices->pluck('id');

        $transactions = FmFeesTransaction::whereIn('fees_invoice_id', $invoiceIds)
            ->with('transcationDetails.transcationFeesType', 'feesInvoiceInfo')
            ->orderBy('created_at')
            ->get();

        $allDetails = $invoices->flatMap->invoiceDetails;

        return [
            'invoices' => $invoices,
            'transactions' => $transactions,
            'totalBilled' => (float) $allDetails->sum('amount'),
            'totalPaid' => (float) $allDetails->sum('paid_amount'),
            'totalDue' => (float) $allDetails->sum('due_amount'),
        ];
    }

    /**
     * Sum of everything still unpaid on invoices tied to a student's earlier
     * (already-promoted-away) StudentRecords - i.e. balance carried over from a
     * prior academic year, invisible to balanceBreakdownFor() because FmFeesInvoice
     * is globally scoped to the current academic_id.
     */
    protected function priorYearBalanceFor(SmStudent $student)
    {
        $priorRecordIds = StudentRecord::where('student_id', $student->id)
            ->where('school_id', $student->school_id)
            ->where('is_promote', 1)
            ->pluck('id');

        if ($priorRecordIds->isEmpty()) {
            return 0;
        }

        $invoiceIds = FmFeesInvoice::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $student->school_id)
            ->whereIn('record_id', $priorRecordIds)
            ->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return 0;
        }

        return (float) FmFeesInvoiceChield::where('school_id', $student->school_id)
            ->whereIn('fees_invoice_id', $invoiceIds)
            ->sum('due_amount');
    }

    /**
     * Sum of paid_amount across every chield on the student's current-course
     * invoices - tuition/misc lines and items alike.
     */
    private function totalAmountPaidFor(SmStudent $student, $invoiceIds)
    {
        return (float) FmFeesInvoiceChield::where('school_id', $student->school_id)
            ->whereIn('fees_invoice_id', $invoiceIds)
            ->sum('paid_amount');
    }
}
