<?php

namespace App\Repositories\SuperAdmin;

use App\Models\Setting;
use App\Repositories\Contracts\SuperAdmin\SettingsRepositoryInterface;
use Illuminate\Support\Facades\DB;

class SettingsRepository implements SettingsRepositoryInterface
{
    public function get(string $group, string $key, string $scopeType = 'platform', ?int $scopeId = null): ?Setting
    {
        return Setting::query()
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('group', $group)
            ->where('key', $key)
            ->first();
    }

    public function getGroup(string $group, string $scopeType = 'platform', ?int $scopeId = null): array
    {
        $rows = Setting::query()
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('group', $group)
            ->get(['key', 'value']);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->key] = $row->value;
        }

        return $out;
    }

    public function setMany(
        string $group,
        array $values,
        string $scopeType = 'platform',
        ?int $scopeId = null,
        array $encryptedKeys = []
    ): void {
        DB::transaction(function () use ($group, $values, $scopeType, $scopeId, $encryptedKeys) {
            foreach ($values as $key => $value) {
                $this->setOne(
                    group: $group,
                    key: (string)$key,
                    value: $value,
                    scopeType: $scopeType,
                    scopeId: $scopeId,
                    encrypted: in_array((string)$key, $encryptedKeys, true)
                );
            }
        });
    }

    public function setOne(
        string $group,
        string $key,
        mixed $value,
        string $scopeType = 'platform',
        ?int $scopeId = null,
        bool $encrypted = false
    ): void {
        Setting::query()->updateOrCreate(
            [
                'scope_type' => $scopeType,
                'scope_id'   => $scopeId,
                'group'      => $group,
                'key'        => $key,
            ],
            [
                'value'        => $value,
                'is_encrypted' => $encrypted,
            ]
        );
    }
}
