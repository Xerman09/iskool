<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentPlanInstallment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function planAssign()
    {
        return $this->belongsTo('App\PaymentPlanAssign', 'payment_plan_assign_id', 'id');
    }
}
