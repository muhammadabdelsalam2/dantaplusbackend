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

    public static function normalize(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }

        return $role === self::LEGACY_LAB_ROLE
            ? LabRole::LabAdmin->value
            : $role;
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
