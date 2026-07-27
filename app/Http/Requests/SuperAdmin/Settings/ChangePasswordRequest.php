<?php

namespace App\Http\Requests\SuperAdmin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'min:6'],
            'new_password' => ['required_without:password', 'string', 'min:8', 'confirmed'],
            'password' => ['required_without:new_password', 'string', 'min:8', 'confirmed'],
        ];
    }
}
