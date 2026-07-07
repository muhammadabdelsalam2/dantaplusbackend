<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayClinicOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', Rule::in(['cash'])],
            'payment_status' => ['nullable', Rule::in(['paid', 'pending_cash', 'pending_payment', 'pending_invoice'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.in' => 'Only cash payment is allowed for material orders.',
        ];
    }
}
