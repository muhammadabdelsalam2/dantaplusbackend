<?php

namespace Database\Seeders;

use App\Models\Clinic;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Clinic::create([
                'name' => 'Clinic ' . $i,
                'owner_name' => 'Owner ' . $i,
                'email' => 'clinic' . $i . '@example.com',
                'phone' => '0100000' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                'address' => 'Address ' . $i,
                'subscription_plan' => 'Premium',
                'payment_method' => 'Cash',
                'status' => 'active',
                'start_date' => now()->subMonths(2),
                'expiry_date' => now()->addMonths(10),
                'max_users' => 10,
                'max_branches' => 3,
            ]);
        }
    }
}
