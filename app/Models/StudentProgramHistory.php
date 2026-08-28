<?php

namespace App\Models;

use App\Course;
use App\SmStudent;
use App\CurriculumVersion;
use App\User;
use Illuminate\Database\Eloquent\Model;

class StudentProgramHistory extends Model
{
    protected $table = 'sm_student_program_histories';

    protected $guarded = ['id'];

    protected $casts = [
        'student_id' => 'integer',
        'previous_course_id' => 'integer',
        'current_course_id' => 'integer',
        'previous_curriculum_version_id' => 'integer',
        'current_curriculum_version_id' => 'integer',
        'changed_by' => 'integer',
        'school_id' => 'integer',
        'academic_id' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(SmStudent::class, 'student_id', 'id');
    }

    public function previousCourse()
    {
        return $this->belongsTo(Course::class, 'previous_course_id', 'id');
    }

    public function currentCourse()
    {
        return $this->belongsTo(Course::class, 'current_course_id', 'id');
    }

    public function previousCurriculumVersion()
    {
        return $this->belongsTo(CurriculumVersion::class, 'previous_curriculum_version_id', 'id');
    }

    public function currentCurriculumVersion()
    {
        return $this->belongsTo(CurriculumVersion::class, 'current_curriculum_version_id', 'id');
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by', 'id');
    }
}
