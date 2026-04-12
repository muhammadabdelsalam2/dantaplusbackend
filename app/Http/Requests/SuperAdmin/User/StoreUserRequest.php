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
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'role' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === 'lab') {
                        return;
                    }

                    if (! \Spatie\Permission\Models\Role::query()->where('name', $value)->where('guard_name', 'web')->exists()) {
                        $fail('The selected role is invalid.');
                    }
                },
            ],
            'lab_id' => ['sometimes', 'nullable', 'integer', Rule::exists('dental_labs', 'id')],
            'lab_name' => ['sometimes', 'nullable', 'string', 'max:255'],
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
