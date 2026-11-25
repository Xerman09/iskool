<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * What one year level costs in tuition, for one academic year.
 *
 * Basic education's counterpart to Course::price_per_unit. No global scope on
 * purpose: the Student Account projects the whole of a learner's schooling,
 * which reaches across academic years, and a scope pinned to the open one would
 * price every future year at nothing. Callers say which year they mean.
 */
class ClassTuition extends Model
{
    protected $table = 'class_tuitions';

    protected $fillable = ['class_id', 'academic_id', 'amount', 'school_id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function class()
    {
        return $this->belongsTo(SmClass::class, 'class_id', 'id');
    }

    /**
     * The tuition in force for a year level, for one academic year.
     *
     * Falls back to the most recent year that has an amount, so a school that
     * sets its fees once and rolls the year over is not billed nothing until
     * somebody re-enters them. Returns 0.0 when no amount has ever been set -
     * which is a school that has not finished its setup, not a free year level,
     * and the screens say so rather than billing it.
     */
    public static function amountFor($classId, $academicId, $schoolId)
    {
        if (!$classId) {
            return 0.0;
        }

        $exact = static::where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->where('academic_id', $academicId)
            ->value('amount');

        if ($exact !== null) {
            return (float) $exact;
        }

        return (float) (static::where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->orderByDesc('academic_id')
            ->value('amount') ?: 0);
    }
}
