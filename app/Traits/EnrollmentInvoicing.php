<?php

namespace App\Traits;

use App\SmItem;
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

    protected function tuitionFeesType()
    {
        return $this->feesTypeNamed('Tuition');
    }

    protected function storeItemsFeesType()
    {
        return $this->feesTypeNamed('Store Items');
    }

    /**
     * Add one payable line to an existing invoice. Shared by the item-purchase path
     * (sm_item_id/quantity set) and every plain fee line (tuition, down payment,
     * tuition installment) - those just leave item/quantity null.
     */
    protected function addChieldLine(FmFeesInvoice $invoice, FmFeesType $feesType, $amount, ?SmItem $item = null, ?int $quantity = null)
    {
        $chield = new FmFeesInvoiceChield();
        $chield->fees_invoice_id = $invoice->id;
        $chield->fees_type = $feesType->id;
        $chield->sm_item_id = optional($item)->id;
        $chield->quantity = $quantity;
        $chield->amount = $amount;
        $chield->sub_total = $amount;
        $chield->paid_amount = 0;
        $chield->due_amount = $amount;
        $chield->note = optional($item)->item_name;
        $chield->school_id = auth()->user()->school_id;
        $chield->academic_id = getAcademicId();
        $chield->save();

        $this->recalculateInvoicePaymentStatus($invoice);

        return $chield;
    }

    /**
     * Keep the invoice header's payment_status truthful after a new line is added to
     * an invoice that may have already been flagged 'paid' - e.g. a payment plan or
     * item purchase appending onto a down-payment invoice that was already settled.
     * Mirrors the balance formula FeesExtendedController::addFeesAmount() uses.
     */
    protected function recalculateInvoicePaymentStatus(FmFeesInvoice $invoice)
    {
        $balance = ($invoice->Tamount + $invoice->Tfine) - ($invoice->Tpaidamount + $invoice->Tweaver);

        $invoice->payment_status = $balance <= 0
            ? 'paid'
            : ($invoice->Tpaidamount > 0 ? 'partial' : 'unpaid');
        $invoice->save();
    }

    /**
     * Append one item-purchase line to an existing (unpaid) invoice, or a brand new
     * one the caller just created. Unlike tuition/misc, items are always billed at
     * full price - there's no down-payment cap on them - so this just adds a plain
     * payable chield row on top of whatever is already on the invoice.
     */
    protected function addItemLine(FmFeesInvoice $invoice, SmItem $item, int $quantity)
    {
        $unitPrice = (float) ($item->unit_price ?? 0);
        $amount = round($unitPrice * $quantity, 2);

        return $this->addChieldLine($invoice, $this->storeItemsFeesType(), $amount, $item, $quantity);
    }

    /**
     * Start a brand new invoice for this record - one per order/checkout. Used both
     * for the initial itemized enrollment invoice (type 'fees') and any later,
     * standalone item-store checkout (type 'store').
     */
    protected function newOrderInvoice(SmStudent $student, StudentRecord $record, $type, $dueInDays = 7)
    {
        $invoice = new FmFeesInvoice();
        $invoice->class_id = $student->class_id;
        $invoice->course_id = $student->course_id;
        $invoice->semester_id = $student->semester_id;
        $invoice->type = $type;
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

        return $invoice;
    }

    /**
     * The most recent invoice for this record that's still "open" for this order -
     * nothing paid on any of its lines yet, and no payment plan attached - so a new
     * line (an item, say) still belongs on it. Once either happens, that invoice is
     * considered closed/locked and anything new starts a fresh invoice instead.
     */
    protected function openOrderInvoiceFor(StudentRecord $record)
    {
        return FmFeesInvoice::where('record_id', $record->id)
            ->whereIn('type', ['fees', 'store'])
            ->whereDoesntHave('invoiceDetails', fn ($q) => $q->where('paid_amount', '>', 0))
            ->whereDoesntHave('paymentPlanAssign')
            ->orderByDesc('id')
            ->first();
    }
}
