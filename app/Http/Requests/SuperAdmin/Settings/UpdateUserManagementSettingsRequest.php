<?php
// UpdateUserManagementSettingsRequest.php
namespace App\Http\Requests\SuperAdmin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserManagementSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'allow_new_signups' => ['sometimes', 'boolean'],
            'allow_trial_accounts' => ['sometimes', 'boolean'],
        ];
    }
}
