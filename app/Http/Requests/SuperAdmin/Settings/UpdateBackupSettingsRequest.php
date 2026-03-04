<?php
// UpdateBackupSettingsRequest.php
namespace App\Http\Requests\SuperAdmin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBackupSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'auto_backup_frequency' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly', 'off'])],
        ];
    }
}
