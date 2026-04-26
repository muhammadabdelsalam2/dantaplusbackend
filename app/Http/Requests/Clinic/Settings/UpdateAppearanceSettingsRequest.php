<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppearanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['required', 'string', Rule::in(['light', 'dark'])],
            'primary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'language' => ['required', 'string', Rule::in(['en', 'ar', 'english', 'arabic'])],
        ];
    }
}
