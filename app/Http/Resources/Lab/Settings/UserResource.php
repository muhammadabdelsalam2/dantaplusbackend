<?php

namespace App\Http\Resources\Lab\Settings;

use App\Enums\LabRole;
use App\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->role?->value ?: ($this->getRoleNames()->first() ?? null);
        $status = $this->status?->value ?: ($this->is_active ? UserStatus::Active->value : UserStatus::Inactive->value);

        $username = $this->username;
        if (!$username && $this->name) {
            $username = Str::lower(preg_replace('/\s+/', '', trim((string) $this->name)));
            $username = preg_replace('/[^a-z0-9_\.]/', '', (string) $username);
        }

        $commissionRates = $role === LabRole::LabTechnician->value
            ? ($this->commission_rates ?? [])
            : null;

        return [
            'id' => $this->id,
            'full_name' => $this->name ?? '',
            'username' => $username ?? '',
            'email' => $this->email ?? '',
            'role' => $role ?? '',
            'status' => $status ?? '',
            'avatar_url' => $this->avatar_url ?? '',
            'last_login' => optional($this->last_login_at)->toISOString() ?? '',
            'commission_rates' => $commissionRates,
        ];
    }
}
