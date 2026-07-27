<?php

namespace App\Services\SuperAdmin;

use App\Repositories\Contracts\SuperAdmin\SettingsRepositoryInterface;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'name'  => $data['full_name'] ?? $data['name'] ?? $user->name,
            'username' => $data['username'] ?? $user->username,
            'email' => $data['email'] ?? $user->email,
        ])->save();

        return $user->fresh();
    }

    public function profilePayload(User $user): array
    {
        return [
            'full_name' => $user->name,
            'username' => $user->username ?: $this->getSuggestedUsername($user),
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'avatar' => $user->avatar_url,
        ];
    }

    public function updateAvatar(User $user, array $upload): User
    {
        $user->avatar_url = $upload['url'] ?? $upload['path'] ?? null;
        $user->save();

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

    public function generalSettings(): array
    {
        $data = $this->getGroup('global');

        return [
            'system_name' => $data['system_name'] ?? 'Denta+ SaaS',
            'support_email' => $data['support_email'] ?? 'support@dentaplus.com',
            'support_phone' => $data['support_phone'] ?? '1-800-555-DENT',
            'system_favicon' => $data['system_favicon'] ?? null,
            'system_logo' => $data['system_logo'] ?? null,
            'timezone' => $data['timezone'] ?? config('app.timezone'),
            'default_language' => $data['default_language'] ?? 'English',
            'default_currency' => $data['default_currency'] ?? 'USD',
            'filters' => [
                'timezones' => \DateTimeZone::listIdentifiers(),
                'languages' => ['English', 'Arabic'],
                'currencies' => ['USD', 'EGP', 'SAR', 'AED'],
            ],
        ];
    }

    public function updateGeneralSettings(array $values): array
    {
        return $this->updateGroup('global', Arr::only($values, [
            'system_name',
            'support_email',
            'support_phone',
            'system_favicon',
            'system_logo',
            'timezone',
            'default_language',
            'default_currency',
        ]));
    }

    public function whatsappSettings(): array
    {
        $data = $this->getGroup('whatsapp');

        return [
            'device_status' => $data['device_status'] ?? 'Disconnected',
            'base_url' => config('services.wasender.base_url'),
            'api_key' => $this->maskSecret(config('services.wasender.api_key')),
            'device_id' => config('services.wasender.device_id'),
            'message_templates' => $this->whatsappTemplates(),
            'webhook_url' => url('/api/whatsapp/webhook'),
        ];
    }

    public function updateWhatsappRuntimeSettings(array $values): array
    {
        $this->updateGroup('whatsapp', Arr::only($values, ['device_status']));

        return $this->whatsappSettings();
    }

    public function whatsappTemplates(): array
    {
        $stored = $this->getGroup('whatsapp_templates');
        $defaults = [
            'queue_arrival' => [
                'name' => 'Queue Arrival',
                'content' => 'Hello {{patient_name}}, your turn is approaching at {{clinic_name}}.',
            ],
        ];

        foreach ($stored as $key => $value) {
            if (is_array($value)) {
                $defaults[$key] = array_merge($defaults[$key] ?? ['name' => Str::headline($key)], $value);
            } else {
                $defaults[$key] = [
                    'name' => $defaults[$key]['name'] ?? Str::headline($key),
                    'content' => (string) $value,
                ];
            }
        }

        return collect($defaults)
            ->map(fn (array $template, string $key) => [
                'key' => $key,
                'name' => $template['name'],
                'content' => $template['content'],
                'placeholders' => ['{{patient_name}}', '{{clinic_name}}', '{{order_id}}'],
            ])
            ->values()
            ->all();
    }

    public function saveWhatsappTemplate(string $templateKey, array $values): array
    {
        $this->updateGroup('whatsapp_templates', [
            $templateKey => [
                'name' => $values['name'] ?? Str::headline($templateKey),
                'content' => $values['content'],
            ],
        ]);

        return $this->whatsappTemplates();
    }

    public function billingSettings(): array
    {
        $data = $this->getGroup('billing_plans');

        return [
            'plans' => [
                'premium' => $data['premium'] ?? [
                    'plan_name' => 'Premium',
                    'yearly_price' => 990,
                    'monthly_price' => 99,
                    'description' => 'All features for large-scale operations.',
                ],
                'standard' => $data['standard'] ?? [
                    'plan_name' => 'Standard',
                    'yearly_price' => 590,
                    'monthly_price' => 59,
                    'description' => 'Advanced features for growing clinics.',
                ],
                'basic' => $data['basic'] ?? [
                    'plan_name' => 'Basic',
                    'yearly_price' => 290,
                    'monthly_price' => 29,
                    'description' => 'Essential features for small clinics.',
                ],
            ],
            'view_subscriptions' => [
                'url' => '/owner/invoices',
            ],
            'stripe_payment_gateway' => [
                'dashboard_url' => config('services.stripe.dashboard_url'),
            ],
        ];
    }

    public function userManagementSettings(): array
    {
        $data = $this->getGroup('user_management');

        return [
            'allow_new_signups' => (bool) ($data['allow_new_signups'] ?? true),
            'allow_trial_accounts' => (bool) ($data['allow_trial_accounts'] ?? true),
            'default_permissions' => [
                'status' => 'coming_soon',
                'disabled' => true,
            ],
        ];
    }

    public function notificationSettings(): array
    {
        $data = $this->getGroup('notifications');

        return [
            'enable_system_email_notifications' => (bool) ($data['enable_system_email_notifications'] ?? true),
            'enable_sms_whatsapp_notifications' => (bool) ($data['enable_sms_whatsapp_notifications'] ?? false),
            'notification_sounds' => (bool) ($data['notification_sounds'] ?? true),
            'twilio_integration' => [
                'status' => 'coming_soon',
                'disabled' => true,
                'account_sid' => null,
                'auth_token' => null,
            ],
        ];
    }

    public function backupSettings(): array
    {
        $data = $this->getGroup('backup');

        return [
            'auto_backup_frequency' => $data['auto_backup_frequency'] ?? 'daily',
            'filters' => [
                'auto_backup_frequencies' => ['daily', 'weekly', 'monthly', 'off'],
            ],
            'api_keys' => [
                'status' => 'coming_soon',
                'disabled' => true,
            ],
            'access_logs' => [
                'status' => 'coming_soon',
                'disabled' => true,
            ],
        ];
    }

    public function customizationSettings(): array
    {
        $data = $this->getGroup('customization');

        return [
            'dashboard_theme' => $data['dashboard_theme'] ?? 'auto',
            'accent_color' => $data['accent_color'] ?? '#6C5CE7',
            'filters' => [
                'dashboard_themes' => ['auto', 'dark', 'light'],
                'accent_colors' => ['#E84393', '#EF4444', '#F59E0B', '#3DC7BE', '#6C5CE7'],
            ],
        ];
    }

    private function maskSecret(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat('•', 12);
    }
}
