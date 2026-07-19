<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'profile' => 'sometimes|array',
            'company_name' => 'sometimes|string|max:255',
            'contact_email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'address' => 'sometimes|nullable|string|max:500',
            'website' => 'sometimes|nullable|url|max:255',
            'description' => 'sometimes|nullable|string',
            'logo' => 'sometimes|nullable|image|max:2048',
            'communication' => 'sometimes|array',
            'automation' => 'sometimes|array',
        ];
    }
}
