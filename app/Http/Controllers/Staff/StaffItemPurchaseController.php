<?php

namespace App\Http\Controllers\Staff;

use App\SmItem;
use App\SmItemOrder;
use App\SmNotification;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentInvoicing;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Employee counterpart to Student\StudentItemPurchaseController - same
 * browse/order/pending-orders flow (and the same itemStoreContent partial), just
 * for the authenticated staff member instead of a student, and with no
 * StudentRecord/course concept to carry along.
 */
class StaffItemPurchaseController extends Controller
{
    use EnrollmentInvoicing;

    public function index()
    {
        try {
            $staff = Auth::user()->staff;
            $schoolId = Auth::user()->school_id;

            $items = SmItem::where('school_id', $schoolId)
                ->whereNotNull('unit_price')
                ->where('total_in_stock', '>', 0)
                ->with('category')
                ->get();

            $pendingOrders = SmItemOrder::where('staff_id', optional($staff)->id)
                ->where('status', 'pending')
                ->with('item')
                ->latest()
                ->get();

            $recentResolvedOrders = SmItemOrder::where('staff_id', optional($staff)->id)
                ->whereIn('status', ['approved', 'rejected'])
                ->with('item', 'invoice')
                ->latest('approved_at')
                ->take(10)
                ->get();

            return view('backEnd.academics.staffItemStore', compact('items', 'staff', 'pendingOrders', 'recentResolvedOrders'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * Same "submit for review" semantics as the student flow - just records the
     * pending order, no invoice line or stock deduction until reception approves.
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
            $staff = Auth::user()->staff;

            if (!$staff) {
                Toastr::error('No staff profile found for this account.', 'Failed');
                return redirect()->back();
            }

            $schoolId = $staff->school_id;
            $orderBatch = (string) Str::uuid();

            foreach ($request->item_id as $i => $itemId) {
                $quantity = (int) $request->quantity[$i];
                $item = SmItem::where('school_id', $schoolId)->findOrFail($itemId);

                if ($item->total_in_stock < $quantity) {
                    Toastr::error("Not enough stock for {$item->item_name}.", 'Failed');
                    return redirect()->back();
                }

                $order = new SmItemOrder();
                $order->staff_id = $staff->id;
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
                'student' => $staff->full_name,
                'count' => count($request->item_id),
            ]), route('item-order-approval'));

            Toastr::success('Order submitted. Reception will review it before it is added to your invoice.', 'Success');
            return redirect()->route('staff-item-store');
        } catch (\Exception $e) {
            Toastr::error($e->getMessage() ?: 'Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

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

    public function cancel($id)
    {
        try {
            $staff = Auth::user()->staff;

            $order = SmItemOrder::where('id', $id)
                ->where('staff_id', optional($staff)->id)
                ->where('status', 'pending')
                ->first();

            if (!$order) {
                Toastr::error('This order can no longer be cancelled.', 'Failed');
                return redirect()->back();
            }

            $order->delete();

            Toastr::success('Order cancelled.', 'Success');
            return redirect()->route('staff-item-store');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
