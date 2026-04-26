<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ConnectClinicIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_token' => ['nullable', 'string', 'max:2048'],
            'refresh_token' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
