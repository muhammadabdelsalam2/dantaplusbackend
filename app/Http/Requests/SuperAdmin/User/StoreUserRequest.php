<?php

namespace App\Http\Requests\SuperAdmin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware handles authorization
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ];
    }

    protected function passedValidation(): void
    {
        // default active
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => 1]);
        } else {
            $this->merge(['is_active' => $this->boolean('is_active') ? 1 : 0]);
        }
    }
}
