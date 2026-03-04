<?php

namespace App\Services\SuperAdmin;

use App\Repositories\Contracts\SuperAdmin\SettingsRepositoryInterface;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    public const SCOPE_PLATFORM = 'platform';

    public function __construct(private SettingsRepositoryInterface $repo)
    {
    }

    /** @return array<string, mixed> */
    public function getGroup(string $group): array
    {
        $data = $this->repo->getGroup($group, self::SCOPE_PLATFORM, null);

        // Decrypt convention: encrypted values stored as ['_enc' => '...']
        foreach ($data as $k => $v) {
            if (is_array($v) && array_key_exists('_enc', $v) && is_string($v['_enc'])) {
                try {
                    $data[$k] = Crypt::decryptString($v['_enc']);
                } catch (\Throwable) {
                    $data[$k] = null;
                }
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<int, string> $encryptedKeys
     * @return array<string, mixed>
     */
    public function updateGroup(string $group, array $values, array $encryptedKeys = []): array
    {
        foreach ($encryptedKeys as $key) {
            if (array_key_exists($key, $values) && $values[$key] !== null && $values[$key] !== '') {
                $values[$key] = ['_enc' => Crypt::encryptString((string)$values[$key])];
            }
        }

        $this->repo->setMany($group, $values, self::SCOPE_PLATFORM, null, $encryptedKeys);

        return $this->getGroup($group);
    }

    public function uploadPublicFile(UploadedFile $file, string $dir): array
    {
        $path = $file->store($dir, 'public');

        return [
            'disk' => 'public',
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ];
    }

    public function uploadFromBase64(string $base64, string $dir, string $filename = 'upload.png'): array
    {
        $raw = preg_replace('/^data:\w+\/\w+;base64,/', '', $base64);
        $binary = base64_decode((string)$raw, true);

        if ($binary === false) {
            throw ValidationException::withMessages(['file_base64' => 'Invalid base64 data.']);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $filename);
        $path = $dir . '/' . uniqid('f_', true) . '_' . $safeName;

        Storage::disk('public')->put($path, $binary);

        return [
            'disk' => 'public',
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ];
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->fill([
            'name'  => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
        ])->save();

        return $user->fresh();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }

        $user->password = Hash::make($newPassword);
        $user->save();
    }

    public function getSuggestedUsername(User $user): string
    {
        $email = (string)$user->email;
        $base = strstr($email, '@', true) ?: $email;

        return preg_replace('/[^a-zA-Z0-9\._-]/', '', $base);
    }
}
