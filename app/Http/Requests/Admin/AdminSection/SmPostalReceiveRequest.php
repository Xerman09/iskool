<?php

namespace App\Http\Requests\Admin\AdminSection;

use Illuminate\Foundation\Http\FormRequest;

class SmPostalReceiveRequest extends FormRequest
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
        $maxFileSize =generalSetting()->file_size*1024;  
        return [
            'from_title' => "required|max:250",
            'reference_no' => "required|max:150",
            'address' => "required|max:250",
            'note'=>"nullable",
            'to_title' => "required|max:250",
            'date' => "required|date",
            // mimes checks the sniffed content, but fileUpload() keeps the uploaded name's
            // extension on the file it writes under public/, so the name has to be checked too.
            // extensions does that against the original name and blocks php uploads outright.
            'file' => "sometimes|nullable|mimes:pdf,doc,docx,jpg,jpeg,png,txt|extensions:pdf,doc,docx,jpg,jpeg,png,txt|max:".$maxFileSize,
        ];
    }
}
