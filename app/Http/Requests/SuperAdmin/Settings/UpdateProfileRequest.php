<?php

namespace App\Http\Requests\SuperAdmin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protected by middleware
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name'  => ['sometimes', 'string', 'min:2', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
        ];
    }
}
