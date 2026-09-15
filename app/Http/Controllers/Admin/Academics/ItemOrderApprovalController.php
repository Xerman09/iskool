<?php

namespace App\Http\Controllers\Admin\Academics;

use App\SmItem;
use App\SmStaff;
use App\SmStudent;
use App\SmItemOrder;
use App\SmNotification;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentInvoicing;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ItemOrderApprovalController extends Controller
{
    use EnrollmentInvoicing;

    public function index(Request $request)
    {
        try {
            $schoolId = Auth::user()->school_id;

            $pendingOrders = SmItemOrder::where('school_id', $schoolId)
                ->where('status', 'pending')
                ->with('item', 'student', 'staff')
                ->orderBy('created_at')
                ->get();

            // Split by who ordered - a student's own order (role == student) vs
            // anyone else's (employees; parents can't order at all, so in practice
            // this side is always staff). Two separate queues so reception isn't
            // hunting for one student's request buried among staff pickups, or the
            // other way around.
            $pendingStudentOrders = $pendingOrders->whereNotNull('student_id');
            $pendingStaffOrders = $pendingOrders->whereNotNull('staff_id');

            return view('backEnd.academics.itemOrderApproval', compact('pendingStudentOrders', 'pendingStaffOrders'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * The only point an item order actually becomes a billable invoice line and
     * moves stock - everything before this (submitting, sitting pending) is just a
     * request. Stock is re-checked here (with a row lock), not trusted from
     * submission time, since it may have sold out to someone else while this order
     * waited for review.
     */
    public function approve(Request $request, $id)
    {
        try {
            $order = SmItemOrder::where('id', $id)
                ->where('school_id', Auth::user()->school_id)
                ->where('status', 'pending')
                ->firstOrFail();

            $result = $this->approveOne($order);

            // A reused (already-open) invoice needs no further action here, so
            // stay on the queue - that's the common case while clearing several
            // requests in one sitting. A brand-new invoice, though, is a decision
            // point nobody's seen yet (pay now, or split into a plan?) - jump
            // straight to it instead of leaving that to be discovered later,
            // mirroring how the enrollment invoice surfaces the same choice.
            if ($result['startedNewInvoice']) {
                Toastr::success("Order approved. A separate invoice ({$result['invoice']->invoice_id}) was created for this item. Collect payment or assign a payment plan below.", 'Success');
                return redirect()->route('fees.fees-invoice-view', ['id' => $result['invoice']->id, 'state' => 'view']);
            }

            Toastr::success("Order approved and added to invoice {$result['invoice']->invoice_id}.", 'Success');
            return redirect()->back();
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'OUT_OF_STOCK') {
                Toastr::error('Not enough stock left to approve this order.', 'Failed');
                return redirect()->back();
            }
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $order = SmItemOrder::where('id', $id)
                ->where('school_id', Auth::user()->school_id)
                ->where('status', 'pending')
                ->firstOrFail();

            $this->rejectOne($order, $request->reject_reason);

            Toastr::success('Order rejected.', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * Approve several selected orders from one click - the checkbox selection
     * inside a single person's "View Orders" popup, for reception clearing that
     * person's whole cart in one go instead of one approve click per item.
     * Always scoped to one student or one staff member (the popup only ever lists
     * their own orders), so - like the single approve() - at most one new invoice
     * can come out of this: every item in the batch lands on the same
     * freshly-opened invoice, since it stays "open" (no payment/plan yet) for the
     * rest of the loop.
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer',
        ]);

        $schoolId = Auth::user()->school_id;
        $approvedCount = 0;
        $failedItems = [];
        $newInvoice = null;

        foreach ($request->order_ids as $id) {
            $order = SmItemOrder::where('id', $id)
                ->where('school_id', $schoolId)
                ->where('status', 'pending')
                ->first();

            if (!$order) {
                continue;
            }

            try {
                $result = $this->approveOne($order);
                $approvedCount++;
                if ($result['startedNewInvoice']) {
                    $newInvoice = $result['invoice'];
                }
            } catch (\RuntimeException $e) {
                $failedItems[] = optional($order->item)->item_name ?: "#{$order->id}";
            } catch (\Exception $e) {
                $failedItems[] = optional($order->item)->item_name ?: "#{$order->id}";
            }
        }

        if (count($failedItems) > 0) {
            Toastr::error('Could not approve (insufficient stock): ' . implode(', ', $failedItems), 'Failed');
        }

        // Same reasoning as the individual approve(): a reused invoice needs no
        // further action, so stay on the queue; a new one is an unseen decision
        // point (pay now, or split into a plan?) worth jumping straight to.
        if ($newInvoice) {
            Toastr::success("{$approvedCount} order(s) approved. A separate invoice ({$newInvoice->invoice_id}) was created for these items. Collect payment or assign a payment plan below.", 'Success');
            return redirect()->route('fees.fees-invoice-view', ['id' => $newInvoice->id, 'state' => 'view']);
        }

        if ($approvedCount > 0) {
            Toastr::success("{$approvedCount} order(s) approved.", 'Success');
        }

        return redirect()->back();
    }

    public function bulkReject(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer',
        ]);

        $schoolId = Auth::user()->school_id;
        $rejectedCount = 0;

        foreach ($request->order_ids as $id) {
            $order = SmItemOrder::where('id', $id)
                ->where('school_id', $schoolId)
                ->where('status', 'pending')
                ->first();

            if (!$order) {
                continue;
            }

            $this->rejectOne($order, $request->reject_reason);
            $rejectedCount++;
        }

        Toastr::success("{$rejectedCount} order(s) rejected.", 'Success');
        return redirect()->back();
    }

    /**
     * @return array{invoice: \Modules\Fees\Entities\FmFeesInvoice, item: SmItem, startedNewInvoice: bool}
     * @throws \RuntimeException with message 'OUT_OF_STOCK' if stock ran out
     */
    private function approveOne(SmItemOrder $order): array
    {
        $schoolId = $order->school_id;

        // Every order belongs to exactly one of student_id/staff_id - resolve the
        // owner up front so the transaction body below can stay generic (open- or
        // start-an-invoice, add the line, move stock) regardless of which it is.
        if ($order->student_id) {
            $student = SmStudent::where('school_id', $schoolId)->findOrFail($order->student_id);
            $record = StudentRecord::where('id', $order->record_id)->where('school_id', $schoolId)->firstOrFail();

            $openInvoice = fn () => $this->openOrderInvoiceFor($record);
            $newInvoice = fn () => $this->newOrderInvoice($student, $record, 'store');
            $notifyOwner = fn (string $message, string $url) => $this->notifyUser($student->user, $message, $url);
            $newInvoiceMessageKey = 'academics.item_order_approved_new_invoice_notification';
        } else {
            $staff = SmStaff::where('school_id', $schoolId)->findOrFail($order->staff_id);

            $openInvoice = fn () => $this->openStaffOrderInvoiceFor($staff);
            $newInvoice = fn () => $this->newStaffOrderInvoice($staff);
            $notifyOwner = fn (string $message, string $url) => $this->notifyUser($staff->staff_user, $message, $url);
            // Staff have no tuition invoice, so the tuition-specific wording the
            // student notification uses ("already settled or on a payment plan")
            // doesn't apply - a plain "new invoice was created" covers it instead.
            $newInvoiceMessageKey = 'academics.item_order_approved_new_invoice_notification_staff';
        }

        $invoice = null;
        $item = null;
        // Whether the item landed on an existing (still-open) invoice or had to
        // start a brand new one - surfaced afterward to both reception (toast) and
        // the buyer (notification) so a second invoice appearing never looks
        // unexplained. See openOrderInvoiceFor()/openStaffOrderInvoiceFor() for
        // what "open" means.
        $startedNewInvoice = false;

        DB::transaction(function () use ($order, $openInvoice, $newInvoice, &$invoice, &$item, &$startedNewInvoice) {
            $item = SmItem::where('school_id', $order->school_id)->lockForUpdate()->findOrFail($order->item_id);

            if ($item->total_in_stock < $order->quantity) {
                throw new \RuntimeException('OUT_OF_STOCK');
            }

            $existingInvoice = $openInvoice();
            $startedNewInvoice = !$existingInvoice;
            $invoice = $existingInvoice ?: $newInvoice();
            $this->addItemLine($invoice, $item, $order->quantity);

            $item->total_in_stock -= $order->quantity;
            $item->save();

            $order->status = 'approved';
            $order->approved_by = Auth::user()->id;
            $order->approved_at = now();
            $order->fm_fees_invoice_id = $invoice->id;
            $order->save();
        });

        $notifyOwner(
            $startedNewInvoice
                ? __($newInvoiceMessageKey, ['item' => $item->item_name, 'invoice' => $invoice->invoice_id])
                : __('academics.item_order_approved_notification', ['item' => $item->item_name, 'invoice' => $invoice->invoice_id]),
            route('fees.fees-invoice-view', ['id' => $invoice->id, 'state' => 'view'])
        );

        return ['invoice' => $invoice, 'item' => $item, 'startedNewInvoice' => $startedNewInvoice];
    }

    private function rejectOne(SmItemOrder $order, ?string $reason): void
    {
        $order->status = 'rejected';
        $order->reject_reason = $reason;
        $order->approved_by = Auth::user()->id;
        $order->approved_at = now();
        $order->save();

        $message = __('academics.item_order_rejected_notification', [
            'item' => optional($order->item)->item_name,
            'reason' => $order->reject_reason ?: __('academics.no_reason_given'),
        ]);

        if ($order->student_id) {
            $student = SmStudent::where('school_id', $order->school_id)->find($order->student_id);
            if ($student) {
                $this->notifyUser($student->user, $message, route('student-item-store'));
            }
        } else {
            $staff = SmStaff::where('school_id', $order->school_id)->find($order->staff_id);
            if ($staff) {
                $this->notifyUser($staff->staff_user, $message, route('staff-item-store'));
            }
        }
    }

    /**
     * Closed-loop feedback on the item order's outcome - without this, the only
     * way someone learns their order was approved/rejected is by happening to
     * revisit the Item Store page. Mirrors the SmNotification pattern used for
     * leave request approvals (see SmLeaveRequestController) so it surfaces
     * through the same notification bell the rest of the app already uses.
     *
     * $user may be a "withDefault()" placeholder (e.g. SmStaff::staff_user() when
     * a staff record has no linked login) rather than a real null - guard on ->id,
     * not just truthiness, to skip those the same as a genuinely missing user.
     */
    private function notifyUser($user, string $message, ?string $url = null)
    {
        if (!$user || !$user->id) {
            return;
        }

        $notification = new SmNotification();
        $notification->user_id = $user->id;
        $notification->role_id = $user->role_id;
        $notification->date = date('Y-m-d');
        $notification->message = $message;
        $notification->url = $url;
        $notification->school_id = $user->school_id;
        $notification->academic_id = getAcademicId();
        $notification->save();
    }
}
