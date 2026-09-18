<?php

namespace App\Http\Controllers\Staff;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesTransaction;

/**
 * A staff member's own payment history for their item-store purchases -
 * parallel to Student\StudentTransactionLedgerController, since staff
 * previously had no way to see a purchase again once it aged out of the
 * "recent orders" list on the Item Store page itself (StaffItemPurchaseController).
 */
class StaffTransactionHistoryController extends Controller
{
    public function index()
    {
        try {
            $staff = Auth::user()->staff;

            $transactions = FmFeesTransaction::where('staff_id', optional($staff)->id)
                ->where('paid_status', 'approve')
                ->with('feesInvoiceInfo')
                ->latest()
                ->get();

            return view('backEnd.academics.staffTransactionHistory', compact('transactions'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
