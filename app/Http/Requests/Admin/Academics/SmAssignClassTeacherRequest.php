<?php

namespace App\Http\Requests\Admin\Academics;

use Illuminate\Foundation\Http\FormRequest;

class SmAssignClassTeacherRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'course' => "required",
            /* A college re-forms its blocks each semester, so an adviser is named
               per term there. Basic education keeps one adviser for the school
               year - the quarters are grading periods, not new assignments - so
               requiring a term would mean naming the same person four times for
               the same section. Left out rather than asked for. */
            'semester' => isBasicEd() ? "nullable" : "required",
            'class' => "required",
            'section' => "required",
            'teacher' => "required",
        ];
    }
}
