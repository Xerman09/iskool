<?php

namespace App\Traits;

use App\SmStudent;
use App\Models\StudentRecord;
use Modules\Fees\Entities\FmFeesType;
use Modules\Fees\Entities\FmFeesGroup;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesInvoiceChield;

trait EnrollmentInvoicing
{
    protected function enrollmentFeesGroup()
    {
        $group = FmFeesGroup::where('school_id', auth()->user()->school_id)
            ->where('academic_id', getAcademicId())
            ->where('name', 'Tuition Fees')
            ->first();

        if (!$group) {
            $group = new FmFeesGroup();
            $group->name = 'Tuition Fees';
            $group->description = 'Auto-created group for enrollment tuition invoices.';
            $group->school_id = auth()->user()->school_id;
            $group->academic_id = getAcademicId();
            $group->save();
        }

        return $group;
    }

    protected function feesTypeNamed($name)
    {
        $type = FmFeesType::where('school_id', auth()->user()->school_id)
            ->where('academic_id', getAcademicId())
            ->where('name', $name)
            ->first();

        if (!$type) {
            $type = new FmFeesType();
            $type->name = $name;
            $type->fees_group_id = $this->enrollmentFeesGroup()->id;
            $type->type = 'fees';
            $type->school_id = auth()->user()->school_id;
            $type->academic_id = getAcademicId();
            $type->save();
        }

        return $type;
    }

    protected function downPaymentFeesType()
    {
        return $this->feesTypeNamed('Down Payment');
    }

    protected function tuitionInstallmentFeesType()
    {
        return $this->feesTypeNamed('Tuition Installment');
    }

    protected function createInvoiceLine(SmStudent $student, StudentRecord $record, FmFeesType $feesType, $amount, $dueInDays, $paymentPlanAssignId = null, $installmentNo = null)
    {
        $invoice = new FmFeesInvoice();
        $invoice->class_id = $student->class_id;
        $invoice->course_id = $student->course_id;
        $invoice->payment_plan_assign_id = $paymentPlanAssignId;
        $invoice->installment_no = $installmentNo;
        $invoice->record_id = $record->id;
        $invoice->student_id = $student->id;
        $invoice->create_date = now()->toDateString();
        $invoice->due_date = now()->addDays($dueInDays)->toDateString();
        $invoice->payment_status = 'unpaid';
        $invoice->school_id = auth()->user()->school_id;
        $invoice->academic_id = getAcademicId();
        $invoice->save();
        $invoice->invoice_id = feesInvoiceNumber($invoice);
        $invoice->save();

        $chield = new FmFeesInvoiceChield();
        $chield->fees_invoice_id = $invoice->id;
        $chield->fees_type = $feesType->id;
        $chield->amount = $amount;
        $chield->sub_total = $amount;
        $chield->paid_amount = 0;
        $chield->due_amount = $amount;
        $chield->school_id = auth()->user()->school_id;
        $chield->academic_id = getAcademicId();
        $chield->save();

        return $invoice;
    }
}
