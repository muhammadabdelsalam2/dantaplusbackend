<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicCommunicationSmsEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->normalizeNullableFields([
            'sms_api_key',
            'sms_sender_name',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'smtp_from_name',
            'smtp_from_email',
        ]);

        if (array_key_exists('smtp_port', $data) && $data['smtp_port'] === null) {
            $data['smtp_port'] = null;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'sms_api_key' => ['nullable', 'string'],
            'sms_sender_name' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'smtp_from_email' => ['nullable', 'email', 'max:255'],
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
