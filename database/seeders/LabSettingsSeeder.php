<?php

namespace Database\Seeders;

use App\Enums\LabRole;
use App\Enums\UserStatus;
use App\Enums\WhatsAppLogAction;
use App\Enums\WhatsAppProvider;
use App\Models\DentalLab;
use App\Models\LabGalleryImage;
use App\Models\LabService;
use App\Models\LabSetting;
use App\Models\LabWhatsAppApiLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class LabSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $existingLab = DentalLab::query()->first();
        $labAttributes = $existingLab ? ['id' => $existingLab->id] : ['email' => 'contact@precisionlabs.com'];

        $lab = DentalLab::query()->updateOrCreate($labAttributes, [
            'name' => 'Precision Dental Labs',
            'contact_person' => 'Mike Ross',
            'phone' => '555-0201',
            'email' => 'contact@precisionlabs.com',
            'address' => '789 Tech Park, Metropolis',
            'working_hours' => '9am - 6pm, Mon-Fri',
            'status' => DentalLab::STATUS_ACTIVE,
        ]);

        $labRole = Role::firstOrCreate([
            'name' => 'lab',
            'guard_name' => 'web',
        ]);

        $users = [
            [
                'full_name' => 'Lab Admin (Precision)',
                'email' => 'lab.precision@example.com',
                'role' => LabRole::LabAdmin->value,
                'status' => UserStatus::Active->value,
                'username' => 'labprecision',
                'avatar_url' => 'https://cdn.example.com/avatars/u8.png',
                'last_login_at' => '2026-03-16 00:00:00',
                'commission_rates' => null,
            ],
            [
                'full_name' => 'Memo Ahmed',
                'email' => 'memoahmed321@gmail.com',
                'role' => LabRole::LabAdmin->value,
                'status' => UserStatus::Active->value,
                'username' => 'memoahmed',
                'avatar_url' => 'https://cdn.example.com/avatars/default.png',
                'last_login_at' => '2026-03-16 00:00:00',
                'commission_rates' => null,
            ],
            [
                'full_name' => 'Lab Reception',
                'email' => 'lab.reception@example.com',
                'role' => LabRole::LabReceptionist->value,
                'status' => UserStatus::Active->value,
                'username' => 'labreception',
                'avatar_url' => 'https://cdn.example.com/avatars/u10.png',
                'last_login_at' => '2026-03-14 08:30:00',
                'commission_rates' => null,
            ],
            [
                'full_name' => 'Techie Tom',
                'email' => 'tom.tech@precisionlabs.com',
                'role' => LabRole::LabTechnician->value,
                'status' => UserStatus::Active->value,
                'username' => 'techietom',
                'avatar_url' => 'https://cdn.example.com/avatars/u9.png',
                'last_login_at' => '2026-03-15 09:00:00',
                'commission_rates' => [
                    'zirconia' => 10,
                    'pfm' => 8,
                    'emax' => 10,
                    'pmma' => 7,
                    'other' => 5,
                ],
            ],
            [
                'full_name' => 'John Express',
                'email' => 'DEL-001@dentaplus.com',
                'role' => LabRole::DeliveryRep->value,
                'status' => UserStatus::Active->value,
                'username' => 'johnexpress',
                'avatar_url' => 'https://cdn.example.com/avatars/u11.png',
                'last_login_at' => '2026-03-16 07:15:00',
                'commission_rates' => null,
            ],
            [
                'full_name' => 'Speedy Sara',
                'email' => 'DEL-002@dentaplus.com',
                'role' => LabRole::DeliveryRep->value,
                'status' => UserStatus::Active->value,
                'username' => 'speedysara',
                'avatar_url' => 'https://cdn.example.com/avatars/u13.png',
                'last_login_at' => '2026-03-16 06:15:00',
                'commission_rates' => null,
            ],
            [
                'full_name' => 'Rapid Ron',
                'email' => 'DEL-003@dentaplus.com',
                'role' => LabRole::DeliveryRep->value,
                'status' => UserStatus::Inactive->value,
                'username' => 'rapidron',
                'avatar_url' => 'https://cdn.example.com/avatars/u12.png',
                'last_login_at' => '2026-02-28 11:00:00',
                'commission_rates' => null,
            ],
        ];

        foreach ($users as $payload) {
            $user = User::query()->updateOrCreate([
                'email' => $payload['email'],
            ], [
                'name' => $payload['full_name'],
                'username' => $payload['username'],
                'email' => $payload['email'],
                'password' => Hash::make('Lab@12345'),
                'lab_id' => $lab->id,
                'status' => $payload['status'],
                'role' => $payload['role'],
                'commission_rates' => $payload['commission_rates'],
                'avatar_url' => $payload['avatar_url'],
                'last_login_at' => $payload['last_login_at'],
                'is_active' => $payload['status'] === UserStatus::Active->value,
                'is_verified' => true,
            ]);

            if (!$user->hasRole($labRole->name)) {
                $user->assignRole($labRole);
            }
        }

        $services = [
            ['service_name' => 'Zirconia Crown', 'price' => 120.00, 'turnaround_time_days' => 4],
            ['service_name' => 'E-Max Crown', 'price' => 150.00, 'turnaround_time_days' => 5],
            ['service_name' => 'PFM Crown', 'price' => 90.00, 'turnaround_time_days' => 5],
        ];

        foreach ($services as $service) {
            LabService::query()->updateOrCreate([
                'lab_id' => $lab->id,
                'service_name' => $service['service_name'],
            ], [
                'price' => $service['price'],
                'turnaround_time_days' => $service['turnaround_time_days'],
            ]);
        }

        $meta = [
            'whatsapp_business_account_id' => '123456',
            'business_phone_number_id' => '987654',
            'access_token' => Crypt::encryptString('dummy_access_token'),
            'verify_token' => 'my_verify_token',
        ];

        $metaEncrypted = Crypt::encryptString(json_encode($meta, JSON_UNESCAPED_SLASHES));

        LabSetting::query()->updateOrCreate([
            'lab_id' => $lab->id,
        ], [
            'notifications_json' => [
                'new_case_alerts' => ['in_app_notification' => true, 'email_notification' => false],
                'case_update_alerts' => ['in_app_notification' => true, 'email_notification' => true],
            ],
            'whatsapp_provider' => WhatsAppProvider::MetaCloudApi->value,
            'whatsapp_meta_json' => $metaEncrypted,
            'whatsapp_twilio_json' => null,
        ]);

        $gallery = [
            [
                'type' => 'before',
                'url' => 'https://cdn.example.com/labs/l1/before/sample_before.webp',
                'sort_order' => 1,
            ],
            [
                'type' => 'after',
                'url' => 'https://cdn.example.com/labs/l1/after/sample_after.webp',
                'sort_order' => 1,
            ],
        ];

        foreach ($gallery as $image) {
            LabGalleryImage::query()->updateOrCreate([
                'lab_id' => $lab->id,
                'type' => $image['type'],
                'url' => $image['url'],
            ], [
                'disk' => 'public',
                'sort_order' => $image['sort_order'],
                'uploaded_by' => null,
            ]);
        }

        $logs = [
            [
                'action' => WhatsAppLogAction::SettingsUpdated->value,
                'status' => 'Success',
                'details' => 'Provider set to meta_cloud_api.',
                'provider' => WhatsAppProvider::MetaCloudApi->value,
            ],
            [
                'action' => WhatsAppLogAction::TestSent->value,
                'status' => 'Success',
                'details' => 'Sent test to lab admin.',
                'provider' => WhatsAppProvider::MetaCloudApi->value,
            ],
        ];

        foreach ($logs as $log) {
            LabWhatsAppApiLog::query()->updateOrCreate([
                'lab_id' => $lab->id,
                'action' => $log['action'],
                'provider' => $log['provider'],
                'details' => $log['details'],
            ], [
                'status' => $log['status'],
                'created_at' => now()->subDays(1),
            ]);
        }

        $this->command->info('Lab settings seeded successfully.');
    }
}
