<?php

namespace App\Http\Requests\SuperAdmin\User;

use App\Support\UserRoleManager;
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

        if ($this->has('role')) {
            $this->merge(['role' => UserRoleManager::normalize($this->input('role'))]);
        }

        if (! $this->has('material_company_id') && $this->has('company_id')) {
            $this->merge(['material_company_id' => $this->input('company_id')]);
        }
    }

    public function rules(): array
    {
        $entityType = UserRoleManager::entityTypeForRole($this->input('role'));

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
            'clinic_id' => [Rule::requiredIf($entityType === 'clinic'), 'nullable', 'integer', Rule::exists('clinics', 'id')],
            'lab_id' => [Rule::requiredIf($entityType === 'lab'), 'nullable', 'integer', Rule::exists('dental_labs', 'id')],
            'material_company_id' => [Rule::requiredIf($entityType === 'material_company'), 'nullable', 'integer', Rule::exists('material_companies', 'id')],
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
