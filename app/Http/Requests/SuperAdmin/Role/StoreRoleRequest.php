<?php

namespace App\Http\Requests\SuperAdmin\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware already guards superadmin
    }

    public function rules(): array
    {
        $guard = 'web';

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:64',
                Rule::unique('roles', 'name')->where(fn ($q) => $q->where('guard_name', $guard)),
            ],

            // optional at create time
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [
                'string',
                'min:2',
                'max:190',
                Rule::exists('permissions', 'name')->where(fn ($q) => $q->where('guard_name', $guard)),
            ],
        ];
    }
}
