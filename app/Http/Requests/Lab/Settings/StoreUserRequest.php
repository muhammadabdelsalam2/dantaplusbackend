<?php

namespace App\Http\Requests\Lab\Settings;

use App\Enums\LabRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $labId = auth()->user()?->lab_id;
        $assignableRoles = \App\Support\UserRoleManager::labRoles();

        return [
            'full_name' => ['nullable', 'string', 'max:120'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->where(fn ($q) => $q->where('lab_id', $labId)),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string', Rule::in($assignableRoles)],
            'commission_rates' => ['nullable', 'array'],
            'commission_rates.*' => ['numeric', 'min:0', 'max:100'],
        ];
    }
}
