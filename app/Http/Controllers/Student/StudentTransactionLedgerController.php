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

            $scope = $request->query('scope') === 'whole_stay' ? 'whole_stay' : 'semester';
            $semesterId = $scope === 'semester'
                ? ($request->query('semester_id') ?: $student->semester_id)
                : null;

            $ledger = $this->ledgerFor($student, $scope, $semesterId);

            $semesters = Semester::where('school_id', $student->school_id)
                ->where('active_status', 1)
                ->orderBy('sort_order')
                ->get();

            return view('backEnd.academics.transactionLedger', array_merge($ledger, [
                'student' => $student,
                'semesters' => $semesters,
                'scope' => $scope,
                'selectedSemesterId' => $semesterId,
            ]));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
