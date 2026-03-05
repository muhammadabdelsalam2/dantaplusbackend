<?php

namespace App\Http\Requests\SuperAdmin\Settings;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGlobalSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'system_name' => ['sometimes', 'string', 'max:255'],
            'support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'support_phone' => ['sometimes', 'nullable', 'string', 'max:50'],

            // uploads (either file or base64)
            'logo_file' => ['sometimes', 'file', 'image', 'max:5120'],
            'logo_base64' => ['sometimes', 'string'],
            'favicon_file' => ['sometimes', 'file', 'max:2048'], // allow ico/png
            'favicon_base64' => ['sometimes', 'string'],

            'default_currency' => ['sometimes', 'string', 'max:10'],
            'default_language' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }
}
