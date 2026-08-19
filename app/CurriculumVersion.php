<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CurriculumVersion extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    public function effectiveAcademicYear()
    {
        return $this->belongsTo('App\SmAcademicYear', 'effective_academic_id', 'id');
    }

    public function school()
    {
        return $this->belongsTo('App\SmSchool', 'school_id', 'id');
    }

    public function subjects()
    {
        return $this->hasMany('App\SmSubject', 'curriculum_version_id', 'id');
    }

    public function students()
    {
        return $this->hasMany('App\SmStudent', 'curriculum_version_id', 'id');
    }
}
