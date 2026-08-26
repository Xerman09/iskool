<?php

namespace App\Http\Controllers\Admin\Academics;

use App\SmGeneralSettings;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;

class DownPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index()
    {
        try {
            $editData = SmGeneralSettings::where('school_id', Auth::user()->school_id)->first();

            return view('backEnd.academics.down_payment', compact('editData'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(Request $request)
    {
        $request->validate([
            'down_payment_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $generalSettData = SmGeneralSettings::where('school_id', Auth::user()->school_id)->firstOrFail();
            $generalSettData->down_payment_amount = $request->down_payment_amount ?: null;
            $generalSettData->save();

            session()->forget('generalSetting');

            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
