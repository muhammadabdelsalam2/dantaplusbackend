<?php

namespace App\Http\Requests\Clinic;

use App\Support\UserRoleManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required_with:password', 'string', 'min:6'],
            'role' => ['required', Rule::in(UserRoleManager::clinicAssignableRoles())],
            'status' => ['nullable', 'in:Active,Inactive'],
        ];
    }
}
