<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Semester;
use App\Traits\EnrollmentBalanceBreakdown;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentTransactionLedgerController extends Controller
{
    use EnrollmentBalanceBreakdown;

    public function index(Request $request)
    {
        try {
            $student = Auth::user()->student;

            if (!$student) {
                Toastr::error('Operation Failed', 'Failed');
                return redirect()->back();
            }

            // Always the logged-in student's own record - never taken from the request.
            return view('backEnd.academics.transactionLedger', array_merge($this->ledgerPageData($student, $request), [
                'ledgerRoute' => 'student-transaction-ledger',
                'ledgerRouteParams' => [],
            ]));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
