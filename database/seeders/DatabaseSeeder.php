<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Users\DoctorSeeder;
use Database\Seeders\Users\PatientSeeder;
use Database\Seeders\Users\SuperAdminSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SuperAdminSeeder::class,
            DoctorSeeder::class,
            PatientSeeder::class,
            ClinicSeeder::class,
            MaterialCompanySeeder::class,
            MaterialProductSeeder::class,
            MaterialOrderSeeder::class,
            CommunicationConversationSeeder::class,
            NotificationSeeder::class,
            NotificationLogSeeder::class,
            FeedbackReportSeeder::class,
            SupportTicketSeeder::class,
            SupportReplySeeder::class,
        ]);
    }
}
