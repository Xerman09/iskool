<?php

namespace App\Http\Controllers\Admin\Academics;

use App\PaymentPlanType;
use App\tableList;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use App\Http\Requests\Admin\Academics\PaymentPlanTypeRequest;

class PaymentPlanTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $paymentPlanTypes = PaymentPlanType::where('school_id', auth()->user()->school_id)->orderBy('id')->get();
            return view('backEnd.academics.payment_plan_type', compact('paymentPlanTypes'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(PaymentPlanTypeRequest $request)
    {
        try {
            $paymentPlanType = new PaymentPlanType();
            $paymentPlanType->name = $request->name;
            $paymentPlanType->number_of_installments = $request->number_of_installments;
            $paymentPlanType->active_status = 1;
            $paymentPlanType->created_by = auth()->user()->id;
            $paymentPlanType->school_id = auth()->user()->school_id;
            $paymentPlanType->academic_id = getAcademicId();
            $paymentPlanType->save();

            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            $paymentPlanType = PaymentPlanType::where('school_id', auth()->user()->school_id)->findOrFail($id);
            $paymentPlanTypes = PaymentPlanType::where('school_id', auth()->user()->school_id)->orderBy('id')->get();
            return view('backEnd.academics.payment_plan_type', compact('paymentPlanType', 'paymentPlanTypes'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(PaymentPlanTypeRequest $request)
    {
        try {
            $paymentPlanType = PaymentPlanType::where('school_id', auth()->user()->school_id)->findOrFail($request->id);
            $paymentPlanType->name = $request->name;
            $paymentPlanType->number_of_installments = $request->number_of_installments;
            $paymentPlanType->updated_by = auth()->user()->id;
            $paymentPlanType->save();

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('payment-plan-type');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $tables = tableList::getTableList('payment_plan_type_id', $id);
            if ($tables == null) {
                PaymentPlanType::where('school_id', auth()->user()->school_id)->where('id', $id)->delete();
                Toastr::success('Operation successful', 'Success');
                return redirect()->route('payment-plan-type');
            }

            $msg = 'This data already used in : ' . $tables . ' Please remove those data first';
            Toastr::error($msg, 'Failed');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
