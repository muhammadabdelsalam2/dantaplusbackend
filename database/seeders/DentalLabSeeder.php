<?php

namespace Database\Seeders;

use App\Models\DentalLab;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DentalLabSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'lab',
            'guard_name' => 'web',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $lab = DentalLab::query()->create([
                'name' => 'Dental Lab ' . $i,
                'contact_person' => 'Lab Contact ' . $i,
                'address' => 'Lab Address ' . $i,
                'city' => 'City ' . $i,
                'phone' => '0107000' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'email' => 'lab' . $i . '@example.com',
                'working_hours' => '9 AM - 6 PM',
                'avg_delivery_days' => 3 + $i,
                'response_speed' => 'Medium',
                'status' => 'Active',
                'rating' => 4.0,
                'is_external' => false,
                'date_added' => now()->subDays(10 + $i),
                'on_time_percentage' => 92.5,
                'rejection_rate' => 3.0,
            ]);

            $user = User::query()->create([
                'name' => 'Lab Admin ' . $i,
                'email' => 'labadmin' . $i . '@example.com',
                'password' => Hash::make('Lab@12345'),
                'is_active' => true,
                'is_verified' => true,
                'lab_id' => $lab->id,
            ]);

            $user->assignRole($role);
        }
    }
}
