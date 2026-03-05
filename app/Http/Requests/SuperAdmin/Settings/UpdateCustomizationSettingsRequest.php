<?php
// UpdateCustomizationSettingsRequest.php
namespace App\Http\Requests\SuperAdmin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomizationSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'dashboard_theme' => ['sometimes', 'string', Rule::in(['light', 'dark', 'auto'])],
            'accent_color' => ['sometimes', 'string', 'max:30'], // e.g. hex or token
        ];
    }
}
