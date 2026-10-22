<?php

namespace App\Http\Requests\Admin\Academics;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ClassRequest extends FormRequest
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
        // Year levels are shared across academic years, so a name must be unique across
        // the whole school rather than within the selected year.
        if(generalSetting()->result_type == 'mark'){
            return [
                'name' => ['required', 'max:200' , Rule::unique('sm_classes', 'class_name')->where('school_id', auth()->user()->school_id)->ignore($this->id)],
                'section' => "required",
                'pass_mark' => "required",
            ];
        }
        else{
            return [
                'name' => ['required', 'max:200' , Rule::unique('sm_classes', 'class_name')->where('school_id', auth()->user()->school_id)->ignore($this->id)],
                'section' => "required",
            ];
        }
        
    }
}
