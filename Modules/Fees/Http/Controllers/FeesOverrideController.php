<?php

namespace Modules\Fees\Http\Controllers;

use App\SmAddIncome;
use App\SmBankAccount;
use App\SmBankStatement;
use App\SmStaff;
use App\SmStudent;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesInvoiceChield;
use Modules\Fees\Entities\FmFeesTransaction;
use Modules\Fees\Entities\FmFeesTransactionChield;
use Modules\Wallet\Entities\WalletTransaction;
use App\Traits\EnrollmentBalanceBreakdown;

/**
 * Putting right what was recorded wrong.
 *
 * A payment posted to the wrong invoice cannot be edited into the right one -
 * money moves an invoice's balance, a student's enrolment standing, the income
 * books and a bank balance all at once, and only taking the whole payment back
 * out returns those to where they were. That is what voidPayment() does, and it
 * is the one correction reception cannot make for themselves, because being able
 * to delete a receipt is being able to delete the evidence of one.
 *
 * Hence super admin only, checked here rather than left to the route: role 1
 * walks past userRolePermission by design (see UserRolePermission), so a
 * permission row would gate everyone except the very role that must not be the
 * only thing standing between a cashier and the books.
 */
class FeesOverrideController extends Controller
{
    use EnrollmentBalanceBreakdown;

    /**
     * The three values the rest of the system reads out of payment_status. The
     * invoice create form writes a different vocabulary of its own ('not',
     * 'partial', 'full') and newOrderInvoice() writes 'unpaid', so an override is
     * held to the set that feesPaymentStore() and the status filters agree on.
     */
    private const STATUSES = ['paid', 'partial', 'unpaid'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if ((int) optional(Auth::user())->role_id !== 1) {
                abort(403);
            }

            return $next($request);
        });
    }

    /**
     * Take a payment back out of the books entirely.
     *
     * Everything feesPaymentStore() wrote is undone in one transaction, so a
     * failure half way cannot leave the invoice credited and the income books
     * not, or the other way round - the old delete ran without one and reported
     * "Operation Failed" from the notification at the end, after the rows were
     * already gone.
     */
    public function voidPayment(Request $request, $id)
    {
        /* Scoped by the model's AcademicSchoolScope, so this only ever reaches a
           payment of this school and this academic year. */
        $transaction = FmFeesTransaction::find($id);

        if (!$transaction) {
            Toastr::error(__('fees::feesModule.payment_not_found'), 'Failed');
            return redirect()->back();
        }

        $invoice = FmFeesInvoice::find($transaction->fees_invoice_id);

        if (!$invoice) {
            Toastr::error(__('fees::feesModule.payment_not_found'), 'Failed');
            return redirect()->back();
        }

        try {
            $outcome = DB::transaction(function () use ($transaction, $invoice) {
                $lines = FmFeesTransactionChield::where('fees_transaction_id', $transaction->id)->get();

                /* Read before the delete: what a line's waiver should fall back to is
                   whatever an earlier payment on the same fees type set, and after the
                   cascade there would be no way to ask. */
                $otherTransactionIds = FmFeesTransaction::where('fees_invoice_id', $invoice->id)
                    ->where('id', '!=', $transaction->id)
                    ->pluck('id');

                $totalPaid = 0.0;

                foreach ($lines as $line) {
                    $totalPaid += (float) $line->paid_amount;

                    $chield = FmFeesInvoiceChield::where('fees_invoice_id', $invoice->id)
                        ->where('fees_type', $line->fees_type)
                        ->first();

                    if (!$chield) {
                        continue;
                    }

                    /* paid_amount and fine were added to by this payment, so they come
                       straight back off. Floored at zero: an invoice edited between
                       payment and void can leave less on the line than this payment put
                       there, and a negative paid amount would read as money owed to the
                       student. */
                    $chield->paid_amount = max(0, (float) $chield->paid_amount - (float) $line->paid_amount);
                    $chield->fine = max(0, (float) $chield->fine - (float) $line->fine);

                    /* The waiver was overwritten rather than added to, so there is
                       nothing to subtract - it has to be restored to what the last
                       surviving payment on this fees type set, or to nothing if this
                       was the only one. The old delete left it standing, which kept
                       crediting a waiver that arrived with a payment that no longer
                       exists. */
                    $chield->weaver = (float) (FmFeesTransactionChield::whereIn('fees_transaction_id', $otherTransactionIds)
                        ->where('fees_type', $line->fees_type)
                        ->orderByDesc('id')
                        ->value('weaver') ?? 0);

                    $chield->due_amount = max(0, ((float) $chield->amount + (float) $chield->fine)
                        - ((float) $chield->paid_amount + (float) $chield->weaver));
                    $chield->save();
                }

                // Out of the income books. Linked by fees_collection_id, which
                // feesPaymentStore() sets to this transaction's id.
                SmAddIncome::where('fees_collection_id', $transaction->id)->delete();

                $this->reverseBank($transaction, $totalPaid);
                $this->reverseWallet($transaction, $totalPaid);

                // The chields go with it: fees_transaction_id is ON DELETE CASCADE.
                $transaction->delete();

                $status = $this->recomputeStatus($invoice);

                /* A payment that enrolled someone cannot be removed and leave them
                   enrolled. Mirrors the check that let them in - see
                   FeesExtendedController. */
                $reopened = false;
                if ($invoice->student_id) {
                    $reopened = (new FeesExtendedController())
                        ->markStudentPendingIfUnderpaid($invoice->student_id);
                }

                return ['totalPaid' => $totalPaid, 'status' => $status, 'reopened' => $reopened];
            });
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }

        /* Money leaving the books is worth a line in the log naming who did it -
           the rows themselves are gone afterwards. */
        Log::warning('Fees payment voided', [
            'transaction_id' => $id,
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_id,
            'amount' => $outcome['totalPaid'],
            'by_user_id' => Auth::id(),
            'reopened_enrollment' => $outcome['reopened'],
        ]);

        $message = __('fees::feesModule.payment_voided', [
            'amount' => $outcome['totalPaid'],
            'invoice' => $invoice->invoice_id,
            'status' => __('fees.' . $outcome['status']),
        ]);

        if ($outcome['reopened']) {
            $message .= ' ' . __('fees::feesModule.enrollment_reopened');
        }

        Toastr::success($message, 'Success');

        return redirect()->route('fees.fees-invoice-view', ['id' => $invoice->id, 'state' => 'view']);
    }

    /**
     * Set an invoice's payment status by hand.
     *
     * The stored column is a summary of the lines, written when a payment lands,
     * and anything that moved the lines without going through that path left it
     * saying something no longer true - a voided payment being the common case.
     * The default here is therefore to recompute it from the lines; a forced value
     * is for the invoice whose history is already wrong and has to be stated
     * rather than derived.
     */
    public function updateInvoiceStatus(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|integer',
            'payment_status' => 'required|in:paid,partial,unpaid,recompute',
        ]);

        $invoice = FmFeesInvoice::find($request->invoice_id);

        if (!$invoice) {
            Toastr::error(__('fees::feesModule.invoice_not_found'), 'Failed');
            return redirect()->back();
        }

        $was = $invoice->payment_status;

        if ($request->payment_status === 'recompute') {
            $status = $this->recomputeStatus($invoice);
        } else {
            $status = $request->payment_status;
            $invoice->payment_status = $status;
            $invoice->save();
        }

        Log::warning('Invoice payment status overridden', [
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_id,
            'from' => $was,
            'to' => $status,
            'mode' => $request->payment_status,
            'by_user_id' => Auth::id(),
        ]);

        Toastr::success(__('fees::feesModule.invoice_status_updated', [
            'from' => $was ?: '-',
            'to' => __('fees.' . $status),
        ]), 'Success');

        return redirect()->back();
    }

    /**
     * What the lines actually add up to, written back to the summary column. Same
     * arithmetic and the same two labels feesPaymentStore() uses, plus 'unpaid'
     * for the case it never has to express: nothing paid at all, which is where an
     * invoice lands once its only payment is voided.
     */
    private function recomputeStatus(FmFeesInvoice $invoice)
    {
        $paid = (float) $invoice->Tpaidamount;
        $balance = ((float) $invoice->Tamount + (float) $invoice->Tfine) - ($paid + (float) $invoice->Tweaver);

        $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        $invoice->payment_status = $status;
        $invoice->save();

        return $status;
    }

    /**
     * Undo the bank credit, if the payment was one.
     *
     * The statement row is matched on details as well as id: a fees payment
     * records itself in item_sell_id (that column is doing double duty), so an
     * item sell whose own id happened to match would otherwise be deleted along
     * with it.
     */
    private function reverseBank(FmFeesTransaction $transaction, $totalPaid)
    {
        if ($transaction->payment_method !== 'Bank') {
            return;
        }

        $bank = SmBankAccount::where('id', $transaction->bank_id)
            ->where('school_id', $transaction->school_id)
            ->first();

        if ($bank) {
            $bank->current_balance = (float) $bank->current_balance - (float) $totalPaid;
            $bank->save();
        }

        SmBankStatement::where('item_sell_id', $transaction->id)
            ->where('details', 'Fees Payment')
            ->delete();
    }

    /**
     * Give back what a wallet payment took, and take back what a top-up added.
     *
     * To the student, not to the cashier. FmFeesTransaction.user_id is whoever
     * recorded the payment, and the old delete refunded that user - so voiding a
     * student's wallet payment credited the receptionist's own wallet. The owner
     * of the money is the student (or the employee) the invoice belongs to.
     */
    private function reverseWallet(FmFeesTransaction $transaction, $totalPaid)
    {
        $owner = $this->walletOwner($transaction);

        if (!$owner) {
            return;
        }

        if ($transaction->payment_method === 'Wallet' && $totalPaid > 0) {
            $owner->wallet_balance = (float) $owner->wallet_balance + (float) $totalPaid;
            $owner->save();

            $this->logWalletMovement($transaction, $owner, $totalPaid, 'fees_refund', 'Fees payment voided');
        }

        /* A top-up that rode along with the payment goes back out. Written as its
           own movement rather than by deleting the original: the deposit happened,
           and a wallet ledger that loses entries stops reconciling. */
        if ((float) $transaction->add_wallet_money > 0) {
            $owner->wallet_balance = (float) $owner->wallet_balance - (float) $transaction->add_wallet_money;
            $owner->save();

            $this->logWalletMovement(
                $transaction,
                $owner,
                $transaction->add_wallet_money,
                'fees_refund',
                'Fees extra amount reversed'
            );
        }
    }

    private function walletOwner(FmFeesTransaction $transaction)
    {
        if ($transaction->student_id) {
            return User::find(optional(SmStudent::find($transaction->student_id))->user_id);
        }

        if ($transaction->staff_id) {
            return User::find(optional(SmStaff::find($transaction->staff_id))->user_id);
        }

        return null;
    }

    private function logWalletMovement(FmFeesTransaction $transaction, User $owner, $amount, $type, $note)
    {
        $movement = new WalletTransaction();
        $movement->amount = $amount;
        $movement->payment_method = $transaction->payment_method;
        $movement->user_id = $owner->id;
        $movement->type = $type;
        $movement->status = 'approve';
        $movement->note = $note;
        $movement->school_id = $transaction->school_id;
        $movement->academic_id = getAcademicId();
        $movement->save();
    }
}
