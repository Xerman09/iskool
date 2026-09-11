<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmItemOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function student()
    {
        return $this->belongsTo('App\SmStudent', 'student_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo('App\SmItem', 'item_id', 'id');
    }

    public function approvedByStaff()
    {
        return $this->belongsTo('App\SmStaff', 'approved_by', 'user_id');
    }

    public function invoice()
    {
        return $this->belongsTo('Modules\Fees\Entities\FmFeesInvoice', 'fm_fees_invoice_id', 'id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
