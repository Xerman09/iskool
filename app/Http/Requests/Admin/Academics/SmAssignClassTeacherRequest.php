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
            'semester' => "required",
            'class' => "required",
            'section' => "required",
            'teacher' => "required",
        ];
    }
}
