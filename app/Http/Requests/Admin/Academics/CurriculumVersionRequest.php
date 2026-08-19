<?php

namespace App\Http\Requests\Admin\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurriculumVersionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'course_id' => ['required', 'exists:courses,id'],
            'version_label' => ['required', 'max:255', Rule::unique('curriculum_versions', 'version_label')->where('course_id', $this->course_id)->where('school_id', auth()->user()->school_id)->ignore($this->id)],
            'effective_academic_id' => ['sometimes', 'nullable', 'exists:sm_academic_years,id'],
        ];
    }
}
