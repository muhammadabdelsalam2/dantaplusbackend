<?php

namespace Database\Seeders\Users;

use App\Models\Doctor;
use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $doctors = [
            [
                'name' => 'Dr Ahmed Hassan',
                'email' => 'doctor1@clinic.com',
                'specialization' => 'Cardiology',
                'license_number' => 'DOC-1001',
            ],
            [
                'name' => 'Dr Sara Ali',
                'email' => 'doctor2@clinic.com',
                'specialization' => 'Dermatology',
                'license_number' => 'DOC-1002',
            ],
        ];

        foreach ($doctors as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('Doctor@123'),
                    'is_active' => true,
                ]
            );

            if (!$user->hasRole('doctor')) {
                $user->assignRole('doctor');
            }

            Doctor::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization' => $data['specialization'],
                    'license_number' => $data['license_number'],
                ]
            );
        }

    }
}
