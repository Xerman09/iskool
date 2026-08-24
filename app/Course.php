<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function school()
    {
        return $this->belongsTo('App\SmSchool', 'school_id', 'id');
    }

    public function subjects()
    {
        return $this->hasMany('App\SmSubject', 'course_id', 'id');
    }

    public function students()
    {
        return $this->hasMany('App\SmStudent', 'course_id', 'id');
    }
}
