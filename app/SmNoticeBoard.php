<?php

namespace App;

use App\Scopes\StatusAcademicSchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\RolePermission\Entities\InfixRole;

class SmNoticeBoard extends Model
{
    use HasFactory;
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new StatusAcademicSchoolScope);
    }

    public function users()
    {
        return $this->belongsTo('App\User', 'created_by', 'id');
    }

    /* Lists, not single ids - a notice is written once for every programme, year
       level and section it concerns. Cast so they come back as arrays rather than
       JSON strings wherever they are read. */
    protected $casts = [
        'course_ids' => 'array',
        'class_ids' => 'array',
        'section_ids' => 'array',
    ];

    public function semester()
    {
        return $this->belongsTo('App\Semester', 'semester_id', 'id');
    }

    /**
     * Narrow a notice list to the ones a given student is meant to see.
     *
     * A notice that names nothing below role is for everyone in those roles, which
     * is what every notice written before these columns existed looks like - so the
     * rule has to keep them visible, not hide them. Where a notice does name
     * something, it is shown only to students who match, and a student with nothing
     * recorded yet matches only the unrestricted notices rather than everything.
     *
     * The sections are the student's blocks, not the section on their record: in a
     * college that one is advisory and is null, while the sections they actually sit
     * in come from what they registered for - and a student can be in section A for
     * one subject and B for another.
     *
     * Matched with whereJsonContains rather than a LIKE, because a LIKE for "2"
     * against a list of ids also matches 12 - the mistake inform_to was making until
     * it was found.
     *
     * Role targeting stays where it is, in inform_to; this is the cut below role.
     */
    public function scopeForStudent($query, $student = null)
    {
        $courseId = optional($student)->course_id;
        $semesterId = optional($student)->semester_id;
        $classId = optional($student)->class_id;
        $sectionIds = $this->sectionIdsOf($student);

        return $query
            ->where(fn ($q) => $this->matchesList($q, 'course_ids', $courseId ? [$courseId] : []))
            ->where(fn ($q) => $this->matchesList($q, 'class_ids', $classId ? [$classId] : []))
            ->where(fn ($q) => $this->matchesList($q, 'section_ids', $sectionIds))
            ->where(function ($q) use ($semesterId) {
                $q->whereNull('semester_id');
                if ($semesterId) {
                    $q->orWhere('semester_id', $semesterId);
                }
            });
    }

    /**
     * A notice matches when it names nothing, or when it names something the
     * student is.
     *
     * An empty JSON array counts as naming nothing: a form submitted with the field
     * untouched can store [] rather than null, and a notice nobody could ever see is
     * not what "leave it blank for everyone" means.
     */
    private function matchesList($query, $column, array $studentValues)
    {
        $query->whereNull($column)->orWhereJsonLength($column, 0);

        foreach ($studentValues as $value) {
            $query->orWhereJsonContains($column, (int) $value)
                ->orWhereJsonContains($column, (string) $value);
        }

        return $query;
    }

    /**
     * Every section this student actually sits in, by way of the blocks they
     * registered for this year.
     */
    private function sectionIdsOf($student)
    {
        $studentId = optional($student)->id;

        if (!$studentId) {
            return [];
        }

        $blockIds = SmOptionalSubjectAssign::where('student_id', $studentId)
            ->where('academic_id', getAcademicId())
            ->pluck('assign_subject_id')
            ->filter();

        if ($blockIds->isEmpty()) {
            /* Basic education registers no blocks, so the section on the student is
               the only answer there is. */
            return array_filter([optional($student)->section_id]);
        }

        return SmAssignSubject::whereIn('id', $blockIds)
            ->pluck('section_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function getRoleName($role_id)
    {
        try {
            $getRoleName = InfixRole::select('name')
                ->where('id', $role_id)
                ->first();

            if (isset($getRoleName)) {
                return $getRoleName;
            } else {
                return false;
            }
        } catch (\Exception$e) {
            return false;
        }
    }
}
