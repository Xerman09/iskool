<?php

namespace App\Traits;

use App\Course;
use App\SmStudent;
use App\SmSubject;
use App\PaymentPlanAssign;
use Modules\Fees\Entities\FmFeesType;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesInvoiceChield;

trait EnrollmentBalanceBreakdown
{
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

            $semesterLines = collect([[
                'label' => trim('Tuition' . ($semester ? ' — ' . $semester->semester_name : '') . " ({$units} units)"),
                'amount' => $unitsPrice,
            ]]);

            $miscFees = FmFeesType::where('school_id', $student->school_id)
                ->where('course_id', $student->course_id)
                ->where('class_id', $student->class_id)
                ->where('semester_id', $semesterId)
                ->get();

            foreach ($miscFees as $fee) {
                $semesterLines->push(['label' => $fee->name, 'amount' => (float) $fee->amount]);
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
        $totalAmount = $isPerYear ? $total : ($firstSemTotal ?: 0);
        $lines = $isPerYear ? $allLines : $firstSemLines;

        $standardDownPayment = optional(generalSetting())->down_payment_amount;
        $downPayment = $standardDownPayment ? min($standardDownPayment, $totalAmount) : $totalAmount;

        // Only actually paid amounts reduce the balance — an unpaid down payment invoice
        // does not lower what the student still owes.
        $amountPaid = $this->downPaymentAmountPaid($student);
        $remainingBalance = max(0, $totalAmount - $amountPaid);

        $paymentPlan = $this->activePaymentPlanFor($student);

        return [
            'course' => $course,
            'lines' => $lines,
            'totalAmount' => $totalAmount,
            'downPayment' => $downPayment,
            'amountPaid' => $amountPaid,
            'remainingBalance' => $remainingBalance,
            'paymentPlan' => $paymentPlan,
        ];
    }

    private function activePaymentPlanFor(SmStudent $student)
    {
        $assign = PaymentPlanAssign::where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('active_status', 1)
            ->with('planType', 'invoices.invoiceDetails')
            ->latest()
            ->first();

        if (!$assign) {
            return null;
        }

        $paidCount = $assign->invoices->where('payment_status', 'paid')->count();

        $nextUnpaid = $assign->invoices->firstWhere('payment_status', '!=', 'paid');

        $schedule = $assign->invoices->map(function ($invoice) use ($nextUnpaid) {
            $amount = $invoice->invoiceDetails->sum('amount');

            return [
                'installment_no' => $invoice->installment_no,
                'amount' => $amount,
                'due_date' => $invoice->due_date,
                'status' => $invoice->payment_status,
                'is_next' => $nextUnpaid && $invoice->id === $nextUnpaid->id,
                'invoice_id' => $invoice->id,
            ];
        });

        return [
            'name' => optional($assign->planType)->name,
            'total' => $assign->number_of_installments,
            'paid' => $paidCount,
            'schedule' => $schedule,
        ];
    }

    private function downPaymentAmountPaid(SmStudent $student)
    {
        $type = FmFeesType::where('school_id', $student->school_id)
            ->where('academic_id', getAcademicId())
            ->where('name', 'Down Payment')
            ->first();

        if (!$type) {
            return 0;
        }

        $invoiceIds = FmFeesInvoice::where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->pluck('id');

        return (float) FmFeesInvoiceChield::where('school_id', $student->school_id)
            ->where('fees_type', $type->id)
            ->whereIn('fees_invoice_id', $invoiceIds)
            ->sum('paid_amount');
    }
}
