<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentPlanAssign extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function planType()
    {
        return $this->belongsTo('App\PaymentPlanType', 'payment_plan_type_id', 'id');
    }

    public function student()
    {
        return $this->belongsTo('App\SmStudent', 'student_id', 'id');
    }

    public function invoices()
    {
        return $this->hasMany('Modules\Fees\Entities\FmFeesInvoice', 'payment_plan_assign_id', 'id')->orderBy('installment_no');
    }

    public function invoice()
    {
        return $this->belongsTo('Modules\Fees\Entities\FmFeesInvoice', 'fm_fees_invoice_id', 'id');
    }

    public function installments()
    {
        return $this->hasMany('App\PaymentPlanInstallment', 'payment_plan_assign_id', 'id')->orderBy('installment_no');
    }
}
