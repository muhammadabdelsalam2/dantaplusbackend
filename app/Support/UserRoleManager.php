<?php

namespace App\Support;

use App\Enums\LabRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserRoleManager
{
    public const LEGACY_LAB_ROLE = 'lab';
    public const COMPANY_ROLES = [
        'material_company_admin',
    ];
    public const CLINIC_ROLES = [
        'clinic_admin',
        'doctor',
        'patient',
        'demo_user',
        'nurse',
        'accountant',
        'receptionist',
        'staff',
        'sales_rep',
        'delivery_staff',
    ];

    public const MATERIAL_COMPANY_ROLES = [
        'material_company_admin',
    ];

    public static function normalize(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }

        return match ($role) {
            self::LEGACY_LAB_ROLE => LabRole::LabAdmin->value,
            'super_admin' => 'super-admin',
            'Doctor' => 'doctor',
            'Receptionist' => 'receptionist',
            'Accountant' => 'accountant',
            default => $role,
        };
    }

    public static function isLabScopedRole(?string $role): bool
    {
        return in_array(self::normalize($role), self::labRoles(), true);
    }

    public static function labRoles(): array
    {
        return array_map(
            static fn (LabRole $role) => $role->value,
            LabRole::cases(),
        );
    }

    public static function labAssignableRoles(): array
    {
        return [
            LabRole::LabReceptionist->value,
            LabRole::LabTechnician->value,
            LabRole::DeliveryRepresentative->value,
        ];
    }

    public static function companyRoles(): array
    {
        return self::MATERIAL_COMPANY_ROLES;
    }

    public static function clinicRoles(): array
    {
        return self::CLINIC_ROLES;
    }

    public static function isCompanyScopedRole(?string $role): bool
    {
        return in_array(self::normalize($role), self::MATERIAL_COMPANY_ROLES, true);
    }

    public static function isClinicScopedRole(?string $role): bool
    {
        return in_array(self::normalize($role), self::clinicRoles(), true);
    }

    public static function entityTypeForRole(?string $role): ?string
    {
        $role = self::normalize($role);

        if ($role === null || in_array($role, ['super-admin', 'super_admin'], true)) {
            return null;
        }

        if (self::isClinicScopedRole($role)) {
            return 'clinic';
        }

        if (self::isLabScopedRole($role)) {
            return 'lab';
        }

        if (self::isCompanyScopedRole($role)) {
            return 'material_company';
        }

        return null;
    }

    public static function clinicAssignableRoles(): array
    {
        return [
            'doctor',
            'nurse',
            'accountant',
            'receptionist',
            'staff',
        ];
    }

    /**
     * @throws ValidationException
     */
    public static function ensureRoleExists(string $role): Role
    {
        $normalizedRole = self::normalize($role);

        if (! Role::query()->where('name', $normalizedRole)->where('guard_name', 'web')->exists()) {
            throw ValidationException::withMessages([
                'role' => ['The selected role does not exist.'],
            ]);
        }

        return Role::findByName($normalizedRole, 'web');
    }

    public static function primaryRole(User $user): ?string
    {
        $role = $user->getRoleNames()->first();

        if ($role) {
            return self::normalize($role);
        }

        $storedRole = $user->getAttribute('role');

        if ($storedRole instanceof LabRole) {
            return $storedRole->value;
        }

        if (is_string($storedRole) && $storedRole !== '') {
            return self::normalize($storedRole);
        }

        return null;
    }

    public static function allRoles(User $user): Collection
    {
        return $user->getRoleNames()
            ->map(fn (string $role) => self::normalize($role))
            ->filter()
            ->values()
            ->unique()
            ->values();
    }
}
