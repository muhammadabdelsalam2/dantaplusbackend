<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClinicOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['processing', 'pending', 'shipped', 'completed', 'awaiting_clinic_confirmation', 'cancelled'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'visa', 'pay_later'])],
            'payment_status' => ['nullable', Rule::in(['paid', 'pending_cash', 'pending_payment', 'pending_invoice'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
