<?php

namespace App;

use App\Scopes\GlobalAcademicScope;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\StatusAcademicSchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmSubject extends Model
{
    use HasFactory;
    
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new GlobalAcademicScope);
        static::addGlobalScope(new StatusAcademicSchoolScope);
    }

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function curriculumVersion()
    {
        return $this->belongsTo('App\CurriculumVersion', 'curriculum_version_id', 'id');
    }

    public function yearLevel()
    {
        return $this->belongsTo('App\SmClass', 'class_id', 'id')->withoutGlobalScope(StatusAcademicSchoolScope::class);
    }

    public function semester()
    {
        return $this->belongsTo('App\Semester', 'semester_id', 'id');
    }

    public function prerequisites()
    {
        return $this->hasMany('App\SubjectPrerequisite', 'subject_id', 'id');
    }

    public function sourceSubject()
    {
        return $this->belongsTo('App\SmSubject', 'source_subject_id', 'id');
    }

}