<?php

namespace App\Services\Clinic\Settings;

use App\Models\ClinicCommunicationPermission;
use App\Models\User;
use App\Support\ServiceResult;

class CommunicationPermissionService
{
    public const ROLES = [
        'clinic_admin' => 'Admin',
        'doctor' => 'Doctor',
        'receptionist' => 'Receptionist',
        'accountant' => 'Accountant',
    ];

    public function index(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success([
            'items' => collect(self::ROLES)
                ->map(fn (string $label, string $role) => $this->formatPermission($this->permissionFor($clinicId, $role), $label))
                ->values()
                ->all(),
        ], 'Communication permissions fetched successfully');
    }

    public function update(array $items): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        foreach ($items as $item) {
            $role = $this->normalizeRole($item['role']);
            if (! array_key_exists($role, self::ROLES)) {
                continue;
            }

            ClinicCommunicationPermission::query()->updateOrCreate(
                ['clinic_id' => $clinicId, 'role' => $role],
                [
                    'can_send_notes' => (bool) $item['can_send_notes'],
                    'can_send_voice_notes' => (bool) $item['can_send_voice_notes'],
                    'can_access_patient_discussions' => (bool) $item['can_access_patient_discussions'],
                    'can_delete_messages' => (bool) $item['can_delete_messages'],
                ]
            );
        }

        return $this->index();
    }

    public function allows(User $user, string $ability): bool
    {
        if (! $user->clinic_id) {
            return false;
        }

        $role = $this->normalizeRole($user->getRoleNames()->first() ?: (string) $user->role);
        if (! array_key_exists($role, self::ROLES)) {
            return false;
        }

        $permission = $this->permissionFor((int) $user->clinic_id, $role);

        return (bool) $permission->{$ability};
    }

    private function permissionFor(int $clinicId, string $role): ClinicCommunicationPermission
    {
        return ClinicCommunicationPermission::query()->firstOrCreate(
            ['clinic_id' => $clinicId, 'role' => $role],
            $this->defaultPermissions($role)
        );
    }

    private function defaultPermissions(string $role): array
    {
        return [
            'can_send_notes' => true,
            'can_send_voice_notes' => true,
            'can_access_patient_discussions' => true,
            'can_delete_messages' => $role === 'clinic_admin',
        ];
    }

    private function formatPermission(ClinicCommunicationPermission $permission, string $label): array
    {
        return [
            'role' => $permission->role,
            'label' => $label,
            'can_send_notes' => (bool) $permission->can_send_notes,
            'can_send_voice_notes' => (bool) $permission->can_send_voice_notes,
            'can_access_patient_discussions' => (bool) $permission->can_access_patient_discussions,
            'can_delete_messages' => (bool) $permission->can_delete_messages,
        ];
    }

    private function normalizeRole(string $role): string
    {
        return match (strtolower(str_replace(' ', '_', $role))) {
            'admin' => 'clinic_admin',
            default => strtolower(str_replace(' ', '_', $role)),
        };
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
