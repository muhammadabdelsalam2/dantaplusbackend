<?php

namespace App\Http\Requests\Lab\Settings;

use App\Enums\LabRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = (int) $this->route('user');
        $labId = auth()->user()?->lab_id;
        $assignableRoles = \App\Support\UserRoleManager::labAssignableRoles();

        return [
            'full_name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')
                    ->where(fn ($q) => $q->where('lab_id', $labId))
                    ->ignore($userId),
            ],
            'role' => ['sometimes', 'string', Rule::in($assignableRoles)],
            'avatar_url' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'commission_rates' => ['nullable', 'array'],
            'commission_rates.*' => ['numeric', 'min:0', 'max:100'],
        ];
    }
}
