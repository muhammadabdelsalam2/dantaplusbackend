<?php

namespace App\Http\Requests\SuperAdmin\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guard = 'web';
        $roleId = (int) ($this->route('role')?->id ?? 0);

        return [
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:64',
                Rule::unique('roles', 'name')
                    ->ignore($roleId)
                    ->where(fn ($q) => $q->where('guard_name', $guard)),
            ],
        ];
    }
}
