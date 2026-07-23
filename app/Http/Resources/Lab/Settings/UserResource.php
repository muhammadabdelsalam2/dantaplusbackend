<?php

namespace App\Http\Resources\Lab\Settings;

use App\Enums\LabRole;
use App\Enums\UserStatus;
use App\Support\UserRoleManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = UserRoleManager::primaryRole($this->resource);
        $status = $this->status?->value ?: ($this->is_active ? UserStatus::Active->value : UserStatus::Inactive->value);

        $username = $this->username;
        if (!$username && $this->name) {
            $username = Str::lower(preg_replace('/\s+/', '', trim((string) $this->name)));
            $username = preg_replace('/[^a-z0-9_\.]/', '', (string) $username);
        }

        $commissionRates = $role === LabRole::LabTechnician->value
            ? ($this->commission_rates ?? [])
            : null;
        $displayRole = $role === LabRole::DeliveryRepresentative->value ? 'delivery_rep' : $role;

        return [
            'id' => $this->id,
            'user' => [
                'name' => $this->name ?? '',
                'email' => $this->email ?? '',
                'identifier' => $this->email ?? $username ?? '',
            ],
            'full_name' => $this->name ?? '',
            'username' => $username ?? '',
            'email' => $this->email ?? '',
            'role' => $displayRole ?? '',
            'role_value' => $role ?? '',
            'roles' => $this->getRoleNames()->values()->all(),
            'lab_id' => $this->lab_id,
            'status' => $status ?? '',
            'avatar_url' => $this->avatar_url ?? '',
            'last_login' => optional($this->last_login_at)->toISOString() ?? '',
            'commission_rates' => $commissionRates,
        ];
    }
}
