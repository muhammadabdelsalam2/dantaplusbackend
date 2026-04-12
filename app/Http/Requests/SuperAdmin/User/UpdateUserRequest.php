<?php

namespace App\Http\Requests\SuperAdmin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        $userId = $this->route('user')?->id ?? null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'role' => [
                'sometimes',
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
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active') ? 1 : 0]);
        }
    }
}
