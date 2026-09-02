<?php

namespace App\Http\Controllers\Student;

use App\SmItem;
use App\SmItemCategory;
use App\Models\StudentRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\EnrollmentInvoicing;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

            return view('backEnd.academics.itemStore', compact('items', 'student'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

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

            DB::transaction(function () use ($request, $student, $record, $schoolId) {
                $invoice = $this->openOrderInvoiceFor($record) ?: $this->newOrderInvoice($student, $record, 'store');

                foreach ($request->item_id as $i => $itemId) {
                    $quantity = (int) $request->quantity[$i];

                    $item = SmItem::where('school_id', $schoolId)->lockForUpdate()->findOrFail($itemId);

                    if ($item->total_in_stock < $quantity) {
                        throw new \Exception("Not enough stock for {$item->item_name}.");
                    }

                    $this->addItemLine($invoice, $item, $quantity);

                    $item->total_in_stock -= $quantity;
                    $item->save();
                }
            });

            Toastr::success('Items added to your invoice.', 'Success');
            return redirect()->route('student-balance-summary', ['state' => 'view']);
        } catch (\Exception $e) {
            Toastr::error($e->getMessage() ?: 'Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
