<?php

namespace App\Http\Requests\Admin\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentPlanTypeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'max:100', Rule::unique('payment_plan_types', 'name')->where('school_id', auth()->user()->school_id)->ignore($this->id)],
            'number_of_installments' => ['required', 'integer', 'min:1'],
        ];
    }
}
