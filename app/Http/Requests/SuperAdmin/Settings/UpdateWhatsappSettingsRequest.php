<?php

namespace App\Http\Requests\SuperAdmin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsappSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'base_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:255'],   // encrypted
            'device_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'device_status' => ['sometimes', 'string', Rule::in(['Connected', 'Disconnected'])],
        ];
    }
}
