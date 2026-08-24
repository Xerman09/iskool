<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentPlanType extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function assigns()
    {
        return $this->hasMany('App\PaymentPlanAssign', 'payment_plan_type_id', 'id');
    }
}
