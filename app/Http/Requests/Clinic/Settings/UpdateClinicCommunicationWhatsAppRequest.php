<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicCommunicationWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeNullableFields([
            'whatsapp_provider',
            'whatsapp_phone_number_id',
            'whatsapp_business_account_id',
            'whatsapp_access_token',
            'whatsapp_app_id',
            'whatsapp_app_secret',
            'whatsapp_webhook_verify_token',
        ]));
    }

    public function rules(): array
    {
        return [
            'whatsapp_provider' => ['required', 'string', 'max:255'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_access_token' => ['nullable', 'string'],
            'whatsapp_app_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_app_secret' => ['nullable', 'string'],
            'whatsapp_webhook_verify_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function normalizeNullableFields(array $fields): array
    {
        $data = [];

        foreach ($fields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
