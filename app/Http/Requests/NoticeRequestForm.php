<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoticeRequestForm extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {   
        return [
            'notice_title'=>['required'],
            'notice_date'=>['required', 'date'],
            'role' => ['required', 'array'],
            'publish_on'=>['required', 'after_or_equal:notice_date', 'date'],
            /* The audience below role. All optional: a notice that names none of
               them is for everyone in those roles. Each is a list of ids, so the
               ids have to be ids - a nested array would otherwise cast to 1 and
               aim the notice at whatever happens to be row one. */
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer'],
            'section_ids' => ['nullable', 'array'],
            'section_ids.*' => ['integer'],
            'semester_id' => ['nullable', 'integer'],
        ];
    }
}
