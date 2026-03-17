<?php

namespace Database\Seeders;

use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\ClinicLabPartnership;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        $lab = DentalLab::query()->first();
        if (! $lab) {
            $lab = DentalLab::query()->create([
                'name' => 'Demo Dental Lab',
                'contact_person' => 'Lab Manager',
                'address' => '12 Lab Street',
                'city' => 'Cairo',
                'phone' => '01000000001',
                'email' => 'lab@example.com',
                'status' => DentalLab::STATUS_ACTIVE,
            ]);
        }

        $user = User::query()->where('lab_id', $lab->id)->first();
        if (! $user) {
            $user = User::query()->create([
                'name' => 'Lab Admin',
                'email' => 'lab.admin@example.com',
                'password' => bcrypt('password'),
                'lab_id' => $lab->id,
                'is_active' => true,
            ]);
        }

        $patient = Patient::query()->first();
        if (! $patient) {
            $patientUser = User::query()->firstOrCreate([
                'email' => 'patient@example.com',
            ], [
                'name' => 'Patient One',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);

            $patient = Patient::query()->create([
                'user_id' => $patientUser->id,
                'date_of_birth' => now()->subYears(30)->toDateString(),
            ]);
        }

        $doctor = Doctor::query()->first();
        if (! $doctor) {
            $doctorUser = User::query()->firstOrCreate([
                'email' => 'doctor@example.com',
            ], [
                'name' => 'Dr. Hassan',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);

            $doctor = Doctor::query()->create([
                'user_id' => $doctorUser->id,
                'specialty' => 'Dentistry',
            ]);
        }

        $clinics = [
            [
                'name' => 'Bright Smiles Dental',
                'owner_name' => 'Dr. Emily Adams',
                'email' => 'emily.adams@clinic.com',
                'phone' => '555-0111',
                'address' => '123 Main St, Metropolis',
                'subdomain' => 'brightsmiles.dentaplus.com',
                'clinic_type' => 'General Dentist',
                'is_external' => false,
                'notes' => 'Top referral clinic',
                'registration_date' => now()->subYears(2)->toDateString(),
                'status' => 'Active',
                'partnership_status' => 'Active',
                'cases' => 2,
            ],
            [
                'name' => 'Oakview Dental Care',
                'owner_name' => 'Dr. Robert Kim',
                'email' => 'robert.kim@clinic.com',
                'phone' => '555-0112',
                'address' => '78 Oakview Ave, Metropolis',
                'subdomain' => 'oakview.dentaplus.com',
                'clinic_type' => 'Orthodontics',
                'is_external' => false,
                'notes' => 'Long-term partner',
                'registration_date' => now()->subYears(1)->toDateString(),
                'status' => 'Active',
                'partnership_status' => 'Active',
                'cases' => 2,
            ],
            [
                'name' => 'moamen',
                'owner_name' => 'Dr. Moamen',
                'email' => 'moamen@external.com',
                'phone' => '01000000002',
                'address' => '15 Nile St, Cairo',
                'subdomain' => null,
                'clinic_type' => 'Prosthodontics',
                'is_external' => true,
                'notes' => 'External clinic',
                'registration_date' => null,
                'status' => 'Active',
                'partnership_status' => 'Paused',
                'cases' => 2,
            ],
            [
                'name' => 'Dental Health Center',
                'owner_name' => 'Dr. Sarah Lee',
                'email' => 'sarah.lee@clinic.com',
                'phone' => '555-0113',
                'address' => '210 Market Rd, Metropolis',
                'subdomain' => 'dentalhealth.dentaplus.com',
                'clinic_type' => 'Pediatric Dentistry',
                'is_external' => false,
                'notes' => 'Awaiting approval',
                'registration_date' => now()->subMonths(6)->toDateString(),
                'status' => 'Active',
                'partnership_status' => 'Pending',
                'cases' => 0,
            ],
        ];

        foreach ($clinics as $index => $data) {
            $clinic = Clinic::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'owner_name' => $data['owner_name'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'subdomain' => $data['subdomain'],
                    'clinic_type' => $data['clinic_type'],
                    'is_external' => $data['is_external'],
                    'notes' => $data['notes'],
                    'added_by' => $user->id,
                    'registration_date' => $data['registration_date'],
                    'status' => $data['status'],
                    'subscription_plan' => 'Basic',
                    'payment_method' => 'Manual',
                    'start_date' => now()->subMonths(2),
                    'expiry_date' => now()->addMonths(10),
                    'max_users' => 5,
                    'max_branches' => 2,
                ]
            );

            $partnership = ClinicLabPartnership::query()->updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'lab_id' => $lab->id,
                ],
                [
                    'status' => $data['partnership_status'],
                    'partnership_start_date' => now()->subMonths(4)->toDateString(),
                    'total_cases_sent' => $data['cases'],
                    'last_case_date' => $data['cases'] > 0 ? now()->subDays(2)->toDateString() : null,
                    'invited_by' => $user->id,
                ]
            );

            if ($data['cases'] > 0) {
                for ($i = 1; $i <= $data['cases']; $i++) {
                    $caseNumber = 'LO-' . str_pad((string) (($index * 2) + $i), 3, '0', STR_PAD_LEFT);

                    CaseModel::query()->updateOrCreate(
                        ['case_number' => $caseNumber],
                        [
                            'clinic_id' => $clinic->id,
                            'lab_id' => $lab->id,
                            'patient_id' => $patient->id,
                            'dentist_id' => $doctor->id,
                            'status' => CaseModel::STATUS_DELIVERED,
                            'priority' => CaseModel::PRIORITY_NORMAL,
                            'due_date' => now()->addDays(5 + $i),
                            'case_type' => 'Crown',
                            'tooth_numbers' => ['11', '12'],
                            'description' => 'Partnered clinic case '.$caseNumber,
                            'created_by' => $user->id,
                            'delivered_at' => now()->subDays(1),
                        ]
                    );
                }
            }

         $this->command->info(
        'Seeded clinic: '.$clinic->name.' with partnership '.$partnership->status->value);
        }
    }
}
