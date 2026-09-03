<?php

namespace App\Http\Controllers\Admin\Academics;

use App\SmItem;
use App\SmStudent;
use App\SmItemOrder;
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
                ->with('item', 'student')
                ->orderBy('created_at')
                ->get();

            return view('backEnd.academics.itemOrderApproval', compact('pendingOrders'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * The only point an item order actually becomes a billable invoice line and
     * moves stock - everything before this (student submitting, sitting pending)
     * is just a request. Stock is re-checked here (with a row lock), not trusted
     * from submission time, since it may have sold out to someone else while this
     * order waited for review.
     */
    public function approve(Request $request, $id)
    {
        try {
            $schoolId = Auth::user()->school_id;

            $order = SmItemOrder::where('id', $id)
                ->where('school_id', $schoolId)
                ->where('status', 'pending')
                ->firstOrFail();

            $student = SmStudent::where('school_id', $schoolId)->findOrFail($order->student_id);
            $record = StudentRecord::where('id', $order->record_id)->where('school_id', $schoolId)->firstOrFail();

            DB::transaction(function () use ($order, $student, $record) {
                $item = SmItem::where('school_id', $order->school_id)->lockForUpdate()->findOrFail($order->item_id);

                if ($item->total_in_stock < $order->quantity) {
                    throw new \RuntimeException('OUT_OF_STOCK');
                }

                $invoice = $this->openOrderInvoiceFor($record) ?: $this->newOrderInvoice($student, $record, 'store');
                $this->addItemLine($invoice, $item, $order->quantity);

                $item->total_in_stock -= $order->quantity;
                $item->save();

                $order->status = 'approved';
                $order->approved_by = Auth::user()->id;
                $order->approved_at = now();
                $order->fm_fees_invoice_id = $invoice->id;
                $order->save();
            });

            Toastr::success('Order approved and added to the invoice.', 'Success');
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
            $schoolId = Auth::user()->school_id;

            $order = SmItemOrder::where('id', $id)
                ->where('school_id', $schoolId)
                ->where('status', 'pending')
                ->firstOrFail();

            $order->status = 'rejected';
            $order->reject_reason = $request->reject_reason;
            $order->approved_by = Auth::user()->id;
            $order->approved_at = now();
            $order->save();

            Toastr::success('Order rejected.', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
