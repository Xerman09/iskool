<?php

namespace App\Http\Requests\Admin\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'course_name' => ['required', 'max:255', Rule::unique('courses', 'course_name')->where('school_id', auth()->user()->school_id)->ignore($this->id)],
            'course_code' => ['sometimes', 'nullable', 'max:50', Rule::unique('courses', 'course_code')->where('school_id', auth()->user()->school_id)->ignore($this->id)],
            'description' => ['sometimes', 'nullable'],
        ];
    }
}
