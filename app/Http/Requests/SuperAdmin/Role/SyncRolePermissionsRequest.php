<?php

namespace App\Http\Requests\SuperAdmin\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guard = 'web';

        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'string',
                'min:2',
                'max:190',
                Rule::exists('permissions', 'name')->where(fn ($q) => $q->where('guard_name', $guard)),
            ],
        ];
    }
}
