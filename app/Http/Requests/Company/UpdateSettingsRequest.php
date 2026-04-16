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
            'communication' => 'sometimes|array',
            'automation' => 'sometimes|array',
        ];
    }
}
