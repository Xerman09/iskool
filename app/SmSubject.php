<?php

namespace App;

use App\Scopes\GlobalAcademicScope;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\StatusSchoolScope;
use App\Scopes\StatusAcademicSchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmSubject extends Model
{
    use HasFactory;
    
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new GlobalAcademicScope);
        // Registered under the old identifier so the existing
        // withoutGlobalScope(StatusAcademicSchoolScope::class) calls still lift it. The
        // subject catalog and the curriculum built from it are school-level data a college
        // reuses every year, so they are no longer filtered by academic year.
        static::addGlobalScope(StatusAcademicSchoolScope::class, new StatusSchoolScope);
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