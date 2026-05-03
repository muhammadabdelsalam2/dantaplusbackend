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
            'payment_method' => ['nullable', Rule::in(['cash', 'visa', 'pay_later'])],
            'payment_status' => ['nullable', Rule::in(['paid', 'pending_cash', 'pending_payment', 'pending_invoice'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
