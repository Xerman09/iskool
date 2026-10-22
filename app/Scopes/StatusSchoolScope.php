<?php

namespace App\Scopes;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Builder;

/**
 * Same as StatusAcademicSchoolScope minus the academic year condition.
 *
 * School-level reference data that a college reuses every year - the subject
 * catalog, the curriculum subjects built from it and the year levels - must stay
 * visible whichever academic year is selected. A curriculum version spans the
 * whole stay of a cohort, so scoping its subjects to one academic year hid them
 * the moment the school rolled over, and copying them per year churned the ids
 * that prerequisites, minor-subject equivalence and marks are matched on.
 */
class StatusSchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $table = $model->getTable();

        if (!Auth::check()) {
            $builder->where($table . '.active_status', 1);
            return;
        }

        $builder->where($table . '.active_status', 1);

        $isSaasSuperAdmin = moduleStatusCheck('Saas') == true
            && Auth::user()->is_administrator == "yes"
            && Session::get('isSchoolAdmin') == false
            && Auth::user()->role_id == 1;

        if (!$isSaasSuperAdmin) {
            $builder->where($table . '.school_id', Auth::user()->school_id);
        }
    }
}
