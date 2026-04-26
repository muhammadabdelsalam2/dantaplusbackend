<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $paymentMethods = $this->input('payment_methods');

        if (is_string($paymentMethods)) {
            $decoded = json_decode($paymentMethods, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['payment_methods' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'max:10'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'invoice_notes' => ['nullable', 'string', 'max:2000'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
