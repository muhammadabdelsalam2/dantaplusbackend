<?php

namespace Database\Seeders;

use App\Models\CaseModel;
use App\Models\ClinicLabPartnership;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\LabService;
use App\Models\Patient;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class ClinicDentalLabModuleSeeder extends Seeder
{
    private const CLINIC_ID = 46;

    public function run(): void
    {
        Setting::query()->updateOrCreate(
            [
                'scope_type' => 'clinic',
                'scope_id' => self::CLINIC_ID,
                'group' => 'dental_lab_module',
                'key' => 'response_speeds',
            ],
            [
                'value' => ['Fast', 'Medium', 'Slow'],
                'is_encrypted' => false,
            ]
        );

        $patients = Patient::query()
            ->where('clinic_id', self::CLINIC_ID)
            ->orderBy('id')
            ->take(2)
            ->get();

        $doctorId = Doctor::query()
            ->whereHas('user', fn ($query) => $query->where('clinic_id', self::CLINIC_ID))
            ->value('id');

        if ($patients->count() < 2 || ! $doctorId) {
            return;
        }

        $labs = collect([
            [
                'name' => 'Precision Dental Labs',
                'contact_person' => 'Mahmoud Adel',
                'phone' => '01026010001',
                'email' => 'precision.providers26@dentaplus.local',
                'address' => 'Nasr City, Cairo',
                'response_speed' => 'Fast',
                'working_hours' => '9am - 5pm, Mon-Fri',
            ],
            [
                'name' => 'Aesthetic Creations',
                'contact_person' => 'Salma Hany',
                'phone' => '01026010002',
                'email' => 'aesthetic.providers26@dentaplus.local',
                'address' => 'Heliopolis, Cairo',
                'response_speed' => 'Medium',
                'working_hours' => '10am - 6pm, Sun-Thu',
            ],
        ])->map(function (array $labData) {
            $lab = DentalLab::query()->updateOrCreate(
                ['email' => $labData['email']],
                array_merge($labData, [
                    'status' => DentalLab::STATUS_ACTIVE,
                    'is_external' => true,
                ])
            );

            ClinicLabPartnership::query()->updateOrCreate(
                [
                    'clinic_id' => self::CLINIC_ID,
                    'lab_id' => $lab->id,
                ],
                [
                    'status' => ClinicLabPartnership::STATUS_ACTIVE,
                    'partnership_start_date' => now()->subWeeks(2)->toDateString(),
                    'invited_by' => 1,
                ]
            );

            return $lab;
        });

        $services = [
            [
                ['service_name' => 'Zirconium Crown', 'price' => 120, 'turnaround_time_days' => 5],
                ['service_name' => 'E.max Veneer', 'price' => 150, 'turnaround_time_days' => 7],
            ],
            [
                ['service_name' => 'Retainer Fabrication', 'price' => 80, 'turnaround_time_days' => 4],
                ['service_name' => 'Whitening Tray', 'price' => 60, 'turnaround_time_days' => 3],
            ],
        ];

        foreach ($labs as $labIndex => $lab) {
            foreach ($services[$labIndex] as $serviceData) {
                LabService::query()->updateOrCreate(
                    [
                        'lab_id' => $lab->id,
                        'service_name' => $serviceData['service_name'],
                    ],
                    $serviceData
                );
            }
        }

        $labServices = $labs->map(fn (DentalLab $lab) => $lab->labServices()->orderBy('id')->get());

        $this->upsertCase(
            'LO-M-001',
            $labs[0]->id,
            $patients[0]->id,
            $doctorId,
            $labServices[0][0]->service_name ?? 'Zirconium Crown',
            CaseModel::STATUS_DELIVERED,
            now()->subDays(2)->toDateString(),
            now()->subDays(2)->setHour(12),
            'Delivered on time.'
        );

        $this->upsertCase(
            'LO-M-002',
            $labs[0]->id,
            $patients[1]->id,
            $doctorId,
            $labServices[0][1]->service_name ?? 'E.max Veneer',
            CaseModel::STATUS_PENDING,
            now()->addDays(3)->toDateString(),
            null,
            'Awaiting lab confirmation.'
        );

        $this->upsertCase(
            'LO-M-003',
            $labs[1]->id,
            $patients[0]->id,
            $doctorId,
            $labServices[1][0]->service_name ?? 'Retainer Fabrication',
            CaseModel::STATUS_ACCEPTED,
            now()->addDays(1)->toDateString(),
            null,
            'Accepted by lab.'
        );

        $this->upsertCase(
            'LO-M-004',
            $labs[1]->id,
            $patients[1]->id,
            $doctorId,
            $labServices[1][1]->service_name ?? 'Whitening Tray',
            CaseModel::STATUS_PENDING,
            now()->subDay()->toDateString(),
            null,
            'Late order for analytics.'
        );

        foreach ($labs as $lab) {
            ClinicLabPartnership::query()
                ->where('clinic_id', self::CLINIC_ID)
                ->where('lab_id', $lab->id)
                ->update([
                    'total_cases_sent' => CaseModel::query()
                        ->where('clinic_id', self::CLINIC_ID)
                        ->where('lab_id', $lab->id)
                        ->count(),
                    'last_case_date' => CaseModel::query()
                        ->where('clinic_id', self::CLINIC_ID)
                        ->where('lab_id', $lab->id)
                        ->max('created_at'),
                ]);
        }
    }

    private function upsertCase(
        string $caseNumber,
        int $labId,
        int $patientId,
        int $doctorId,
        string $caseType,
        string $status,
        string $dueDate,
        $deliveredAt,
        string $description
    ): void {
        CaseModel::query()->updateOrCreate(
            ['case_number' => $caseNumber],
            [
                'clinic_id' => self::CLINIC_ID,
                'lab_id' => $labId,
                'patient_id' => $patientId,
                'dentist_id' => $doctorId,
                'status' => $status,
                'priority' => CaseModel::PRIORITY_NORMAL,
                'due_date' => $dueDate,
                'case_type' => $caseType,
                'description' => $description,
                'created_by' => 1,
                'delivered_at' => $deliveredAt,
            ]
        );
    }
}
