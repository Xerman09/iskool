<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Traits\EnrollmentBalanceBreakdown;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;

class StudentTransactionLedgerController extends Controller
{
    use EnrollmentBalanceBreakdown;

    public function index()
    {
        try {
            $student = Auth::user()->student;

            if (!$student) {
                Toastr::error('Operation Failed', 'Failed');
                return redirect()->back();
            }

            $ledger = $this->ledgerFor($student);

            return view('backEnd.academics.transactionLedger', array_merge($ledger, [
                'student' => $student,
            ]));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
