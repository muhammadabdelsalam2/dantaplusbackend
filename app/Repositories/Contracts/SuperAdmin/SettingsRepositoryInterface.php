<?php

namespace App\Repositories\Contracts\SuperAdmin;

use App\Models\Setting;

interface SettingsRepositoryInterface
{
    public function get(string $group, string $key, string $scopeType = 'platform', ?int $scopeId = null): ?Setting;

    /** @return array<string, mixed> */
    public function getGroup(string $group, string $scopeType = 'platform', ?int $scopeId = null): array;

    /**
     * @param array<string, mixed> $values
     * @param array<int, string> $encryptedKeys
     */
    public function setMany(
        string $group,
        array $values,
        string $scopeType = 'platform',
        ?int $scopeId = null,
        array $encryptedKeys = []
    ): void;

    public function setOne(
        string $group,
        string $key,
        mixed $value,
        string $scopeType = 'platform',
        ?int $scopeId = null,
        bool $encrypted = false
    ): void;
}
