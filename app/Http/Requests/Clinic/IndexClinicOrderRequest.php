<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexClinicOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'         => ['nullable', 'string', 'max:255'],
            'status'         => ['nullable', Rule::in(['processing', 'pending', 'shipped', 'completed', 'awaiting_clinic_confirmation', 'cancelled'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'visa', 'pay_later'])],
            'payment_status' => ['nullable', Rule::in(['paid', 'pending_cash', 'pending_payment', 'pending_invoice'])],

            'date_from'      => ['nullable', 'date'],
            'date_to'        => ['nullable', 'date', 'after_or_equal:date_from'],

            'min_price'      => ['nullable', 'numeric', 'min:0'],
            'max_price'      => ['nullable', 'numeric', 'min:0'],

            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $min = $this->input('min_price');
            $max = $this->input('max_price');

           
            if ($min !== null && $max !== null && (float) $max < (float) $min) {
                $validator->errors()->add('max_price', 'The max price field must be greater than or equal to min price.');
            }
        });
    }
}
