<?php

namespace App\Http\Requests\Admin\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmSubjectRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {

        $rules = [
            // Subject names may repeat (e.g. "Mathematics" under different codes/tracks) -
            // subject_code is the actual unique identifier, so only that is checked below.
            'subject_name' => ['required', 'max:200'],
            'subject_type' => "required",
            // Units belong to the subject itself now, not to each curriculum entry built from it.
            'units' => ['required', 'numeric', 'min:0'],
            'subject_code' => ['sometimes', 'required', 'max:200' , Rule::unique('sm_subjects', 'subject_code')->where(function ($query) {
                // The catalog is school-wide and no longer per academic year, so a code must be
                // unique across the whole school rather than within the selected year.
                $query->whereNull('course_id')->where('school_id', auth()->user()->school_id);
            })->ignore($this->id)],
        ];

        if (@generalSetting()->result_type == 'mark') {
            $rules += [
                'pass_mark' => 'required',
            ];
        }
        return $rules;
    }
}
