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
            'full_name'  => ['sometimes', 'string', 'min:2', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->has('full_name')) {
            $this->merge(['name' => $this->input('full_name')]);
        }
    }
}
