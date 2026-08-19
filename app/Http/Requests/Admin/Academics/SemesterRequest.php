<?php

namespace App\Http\Requests\Admin\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SemesterRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'semester_name' => ['required', 'max:100', Rule::unique('semesters', 'semester_name')->where('school_id', auth()->user()->school_id)->ignore($this->id)],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
