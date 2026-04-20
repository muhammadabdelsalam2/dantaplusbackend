<?php

namespace App\Http\Requests\Clinic;

use App\Support\UserRoleManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id') ?? $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('users', 'phone')->ignore($userId)],
            'password' => ['sometimes', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required_with:password', 'string', 'min:6'],
            'role' => ['sometimes', Rule::in(UserRoleManager::clinicAssignableRoles())],
            'status' => ['sometimes', 'in:Active,Inactive'],
        ];
    }
}
