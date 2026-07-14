<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Support\UserRoleManager;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modules' => ['required', 'array'],
            'modules.*' => ['string', 'min:1', 'max:190'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $v) {
            $roleId = $this->route('roleId');
$role = $roleId
    ? (is_numeric($roleId)
        ? Role::find($roleId)
        : Role::where('name', $roleId)->first())
    : null;

            $type = null;
            if ($role) {
                $roleName = $role->name;
                if ($roleName === 'patient') {
                    $type = 'patient';
                } elseif (UserRoleManager::isClinicScopedRole($roleName)) {
                    $type = 'clinic';
                } elseif (UserRoleManager::isLabScopedRole($roleName)) {
                    $type = 'lab';
                } elseif (UserRoleManager::isCompanyScopedRole($roleName)) {
                    $type = 'supplier';
                }
            }

            if ($type === null) {
                $v->errors()->add('modules', 'Unable to determine module type for the target role.');
                return;
            }

            $allowed = array_keys(config("frontend_modules.{$type}", []));

            $modules = (array) $this->input('modules', []);

            foreach ($modules as $idx => $m) {
                if (! in_array($m, $allowed, true)) {
                    $v->errors()->add("modules.{$idx}", "Invalid module '{$m}' for role type {$type}.");
                }
            }
        });
    }
}
