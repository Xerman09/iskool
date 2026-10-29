<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Every program a person may actually pick.
     *
     * basicEdProgram() keeps one hidden row per basic-education deployment, so that
     * curriculum entries have a course to point at and stay out of the subject catalog.
     * It was only ever hidden by the Program screens being off the sidebar in that mode -
     * switch a deployment back to tertiary and the placeholder turned up in every Program
     * dropdown as though it were a real degree. It is internal, so it is excluded here
     * rather than wherever it happened to show; find() still resolves it, which is what
     * a basic-education student's own billing needs.
     */
    public function scopeSelectable($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('course_code')->orWhere('course_code', '!=', '__BASIC_ED__');
        });
    }

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
