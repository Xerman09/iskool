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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
