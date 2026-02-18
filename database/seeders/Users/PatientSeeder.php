<?php

namespace Database\Seeders\Users;

use App\Models\patient;
use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $patients = [
            [
                'name' => 'Mohamed Ali',
                'email' => 'patient1@mail.com',
                'phone' => '01012345678',
                'gender' => 'male',
            ],
            [
                'name' => 'Fatma Hassan',
                'email' => 'patient2@mail.com',
                'phone' => '01087654321',
                'gender' => 'female',
            ],
        ];

        foreach ($patients as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('Patient@123'),
                    'is_active' => true,
                ]
            );

            if (!$user->hasRole('patient')) {
                $user->assignRole('patient');
            }

            patient::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'phone' => $data['phone'],
                    'gender' => $data['gender'],
                ]
            );

        }
    }
}
