<?php

namespace App\Http\Requests\Admin\Academics;

use Illuminate\Foundation\Http\FormRequest;

class CurriculumBuilderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'course_id' => ['required', 'exists:courses,id'],
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
            'class_id' => ['required', 'exists:sm_classes,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'source_subject_id' => ['required', 'exists:sm_subjects,id'],
            'units' => ['required', 'numeric', 'min:0'],
            'subject_classification' => ['required', 'in:major,minor'],
            'has_prerequisite' => ['sometimes', 'boolean'],
            'prerequisite_subject_ids' => ['sometimes', 'array'],
            'prerequisite_subject_ids.*' => ['exists:sm_subjects,id'],
        ];
    }
}
