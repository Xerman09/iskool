<?php

namespace App\Http\Controllers\Student;

use App\SmItem;
use App\SmItemOrder;
use App\SmItemCategory;
use App\SmNotification;
use App\User;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentInvoicing;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StudentItemPurchaseController extends Controller
{
    use EnrollmentInvoicing;

    public function index()
    {
        try {
            $student = Auth::user()->student;
            $schoolId = Auth::user()->school_id;

            $items = SmItem::where('school_id', $schoolId)
                ->whereNotNull('unit_price')
                ->where('total_in_stock', '>', 0)
                ->with('category')
                ->get();

            $pendingOrders = SmItemOrder::where('student_id', optional($student)->id)
                ->where('status', 'pending')
                ->with('item')
                ->latest()
                ->get();

            // Passive closed-loop for approve/reject: a student who never opens the
            // notification bell still sees the outcome next time they visit this page.
            $recentResolvedOrders = SmItemOrder::where('student_id', optional($student)->id)
                ->whereIn('status', ['approved', 'rejected'])
                ->with('item', 'invoice')
                ->latest('approved_at')
                ->take(10)
                ->get();

            return view('backEnd.academics.itemStore', compact('items', 'student', 'pendingOrders', 'recentResolvedOrders'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * Submit item picks for reception's review - this only records the request
     * (SmItemOrder, status 'pending'). No invoice line or stock deduction happens
     * yet; that's ItemOrderApprovalController@approve's job, once reception signs
     * off. Stock is only checked here, not reserved, so a student can still be
     * turned away at approval time if it sold out in the meantime.
     */
    public function addToInvoice(Request $request)
    {
        $request->validate([
            'item_id' => 'required|array|min:1',
            'item_id.*' => 'required|exists:sm_items,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|integer|min:1',
        ]);

        try {
            $student = Auth::user()->student;

            if (!$student || !$student->course_id) {
                Toastr::error('You have not been assigned a program yet.', 'Failed');
                return redirect()->back();
            }

            $schoolId = $student->school_id;

            $record = StudentRecord::where('student_id', $student->id)
                ->where('school_id', $schoolId)
                ->where('academic_id', getAcademicId())
                ->where('is_promote', 0)
                ->first();

            if (!$record) {
                Toastr::error('No active student record found.', 'Failed');
                return redirect()->back();
            }

            // Ties every line from this single "Submit Order" click together so
            // the pending list (and reception's approval queue) can show one
            // order with N items instead of a flat, ungrouped item list.
            $orderBatch = (string) Str::uuid();

            foreach ($request->item_id as $i => $itemId) {
                $quantity = (int) $request->quantity[$i];
                $item = SmItem::where('school_id', $schoolId)->findOrFail($itemId);

                if ($item->total_in_stock < $quantity) {
                    Toastr::error("Not enough stock for {$item->item_name}.", 'Failed');
                    return redirect()->back();
                }

                $order = new SmItemOrder();
                $order->student_id = $student->id;
                $order->record_id = $record->id;
                $order->order_batch = $orderBatch;
                $order->item_id = $item->id;
                $order->quantity = $quantity;
                $order->unit_price = $item->unit_price;
                $order->amount = round($item->unit_price * $quantity, 2);
                $order->status = 'pending';
                $order->school_id = $schoolId;
                $order->academic_id = getAcademicId();
                $order->save();
            }

            $this->notifyReception($schoolId, trans_choice('academics.item_order_submitted_notification', count($request->item_id), [
                'student' => $student->full_name,
                'count' => count($request->item_id),
            ]), route('item-order-approval'));

            Toastr::success('Order submitted. Reception will review it before it is added to your invoice.', 'Success');
            return redirect()->route('student-item-store');
        } catch (\Exception $e) {
            Toastr::error($e->getMessage() ?: 'Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * Every receptionist at this school gets their own notification row - there's
     * no "broadcast to a role" primitive in SmNotification, each recipient's own
     * bell only ever reads rows keyed to their own user_id (see SmNotification::
     * notifications()), so one row per receptionist is what actually surfaces this.
     */
    private function notifyReception($schoolId, string $message, ?string $url = null)
    {
        $receptionists = User::where('school_id', $schoolId)->where('role_id', 7)->get();

        foreach ($receptionists as $user) {
            $notification = new SmNotification();
            $notification->user_id = $user->id;
            $notification->role_id = $user->role_id;
            $notification->date = date('Y-m-d');
            $notification->message = $message;
            $notification->url = $url;
            $notification->school_id = $schoolId;
            $notification->academic_id = getAcademicId();
            $notification->save();
        }
    }

    /**
     * A student can withdraw an order only while it's still pending - once
     * reception approves it, it's a real invoice line and stock has already
     * moved, so it's no longer just "their own cart" to clear.
     */
    public function cancel($id)
    {
        try {
            $student = Auth::user()->student;

            $order = SmItemOrder::where('id', $id)
                ->where('student_id', optional($student)->id)
                ->where('status', 'pending')
                ->first();

            if (!$order) {
                Toastr::error('This order can no longer be cancelled.', 'Failed');
                return redirect()->back();
            }

            $order->delete();

            Toastr::success('Order cancelled.', 'Success');
            return redirect()->route('student-item-store');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
