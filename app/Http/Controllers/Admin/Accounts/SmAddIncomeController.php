<?php

namespace App\Http\Controllers\Admin\Accounts;

use App\SmAddIncome;
use App\SmBankAccount;
use App\SmBankStatement;
use App\SmChartOfAccount;
use App\SmPaymentMethhod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Admin\Accounts\SmAddIncomeRequest;

class SmAddIncomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $add_incomes = SmAddIncome::with('paymentMethod','ACHead')->get();
            $income_heads = SmChartOfAccount::where('type', "I")->get();
            $bank_accounts = SmBankAccount::get();
            $payment_methods = SmPaymentMethhod::get();
            return view('backEnd.accounts.add_income', compact('add_incomes', 'income_heads', 'bank_accounts', 'payment_methods'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(SmAddIncomeRequest $request)
    {
        try {
            $destination='public/uploads/add_income/';
           // DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $add_income = new SmAddIncome();
            $add_income->name = $request->name;
            $add_income->income_head_id = $request->income_head;
            $add_income->date =date('Y-m-d',strtotime($request->date));
            $add_income->payment_method_id = $request->payment_method;
            if (paymentMethodName($request->payment_method)) {
                $add_income->account_id = $request->accounts;
            }
            $add_income->amount = $request->amount;
            $add_income->file = fileUpload($request->file,$destination);
            $add_income->description = $request->description;
            $add_income->school_id = Auth::user()->school_id;
            if(moduleStatusCheck('University')){
                $add_income->un_academic_id = getAcademicId();
            }else{
                $add_income->academic_id = getAcademicId();
            }
            $add_income->save();

            if(paymentMethodName($request->payment_method)){
                $bank=SmBankAccount::where('id',$request->accounts)->where('school_id',Auth::user()->school_id)->first();
                $after_balance= $bank->current_balance + $request->amount;

                $bank_statement= new SmBankStatement();
                $bank_statement->amount= $request->amount;
                $bank_statement->after_balance= $after_balance;
                $bank_statement->type= 1;
                $bank_statement->details= $request->name;
                $bank_statement->item_sell_id= $add_income->id;
                $bank_statement->payment_date= date('Y-m-d',strtotime($request->date));
                $bank_statement->bank_id= $request->accounts;
                $bank_statement->school_id= Auth::user()->school_id;
                $bank_statement->payment_method= $request->payment_method;
                $bank_statement->save();

                $bank->current_balance=$after_balance;
                $bank->update();
            }

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
            $add_income = SmAddIncome::find($id);            
            $add_incomes = SmAddIncome::get();
            $income_heads = SmChartOfAccount::get();
            $bank_accounts = SmBankAccount::get();
            $payment_methods = SmPaymentMethhod::get();
            return view('backEnd.accounts.add_income', compact('add_income', 'add_incomes', 'income_heads', 'bank_accounts', 'payment_methods'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(SmAddIncomeRequest $request)
    {
        try {
            $destination =  'public/uploads/add_income/'; 
           // DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            $add_income = SmAddIncome::find($request->id);

            // Capture the ORIGINAL bank-affecting state before it's overwritten below, so the
            // old account's balance can be correctly reversed no matter what this is changed to.
            $previousAccountId = $add_income->account_id;
            $previousAmount = $add_income->amount;
            $wasBankPayment = paymentMethodName($add_income->payment_method_id);

            $add_income->name = $request->name;
            $add_income->income_head_id = $request->income_head;
            $add_income->date = date('Y-m-d', strtotime($request->date));
            $add_income->payment_method_id = $request->payment_method;
            if (paymentMethodName($request->payment_method)) {
                $add_income->account_id = $request->accounts;
            }
            $add_income->amount = $request->amount;
            $add_income->file = fileUpdate($add_income->file,$request->file,$destination);
            $add_income->description = $request->description;
            $add_income->school_id = Auth::user()->school_id;
            if(moduleStatusCheck('University')){
                $add_income->un_academic_id = getAcademicId();
            }else{
                $add_income->academic_id = getAcademicId();
            }
            $add_income->save();

            // Reverse the ORIGINAL account's balance if this was previously a bank income,
            // regardless of what payment method/account it's being changed to now - otherwise
            // switching banks (or switching away from a bank method) leaves the old account
            // permanently inflated by this income's amount.
            if ($wasBankPayment && $previousAccountId) {
                $previousBank = SmBankAccount::where('id', $previousAccountId)
                                ->where('school_id', Auth::user()->school_id)
                                ->first();
                if ($previousBank) {
                    $previousBank->current_balance = $previousBank->current_balance - $previousAmount;
                    $previousBank->update();
                }
            }

            if(paymentMethodName($request->payment_method)){
                SmBankStatement::where('item_sell_id', $request->id)->delete();
                $bank=SmBankAccount::where('id',$request->accounts)->where('school_id',Auth::user()->school_id)->first();
                $after_balance= $bank->current_balance + $request->amount;

                $bank_statement= new SmBankStatement();
                $bank_statement->amount= $request->amount;
                $bank_statement->after_balance= $after_balance;
                $bank_statement->type= 1;
                $bank_statement->details= $request->name;
                $bank_statement->item_sell_id= $add_income->id;
                $bank_statement->payment_date= date('Y-m-d',strtotime($request->date));
                $bank_statement->bank_id= $request->accounts;
                $bank_statement->school_id= Auth::user()->school_id;
                $bank_statement->payment_method= $request->payment_method;
                $bank_statement->save();

                $bank->current_balance=$after_balance;
                $bank->update();
            }

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('add_income');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function delete(Request $request)
    {
        try {

            $add_income = SmAddIncome::find($request->id);
            if ($add_income->file != "") {
                $path = $add_income->file;
                if (file_exists($path)) {
                    unlink($path);
                }
            }
           // DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            if(paymentMethodName($add_income->payment_method_id) && $add_income->account_id){
                $reset_balance = SmBankStatement::where('item_sell_id',$request->id)->sum('amount');
                $bank=SmBankAccount::where('id',$add_income->account_id)->where('school_id',Auth::user()->school_id)->first();
                $after_balance= $bank->current_balance - $reset_balance;
                $bank->current_balance=$after_balance;
                $bank->update();
                SmBankStatement::where('item_sell_id',$request->id)->delete();
            }
            $add_income->delete();

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('add_income');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}