<?php

namespace Database\Seeders;

use App\Models\CaseModel;
use App\Models\CaseAttachment;
use App\Models\Clinic;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CaseSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = Clinic::query()->take(5)->get();
        $labs = DentalLab::query()->take(3)->get();
        $patients = Patient::query()->take(5)->get();
        $doctors = Doctor::query()->take(5)->get();
        $users = User::query()->get();

        if ($clinics->isEmpty() || $labs->isEmpty() || $patients->isEmpty() || $doctors->isEmpty()) {
            $this->command->warn('Missing clinics, labs, patients, or doctors. Seed those first.');
            return;
        }

        $statuses = [
            CaseModel::STATUS_PENDING,
            CaseModel::STATUS_ACCEPTED,
            CaseModel::STATUS_IN_PROGRESS,
            CaseModel::STATUS_COMPLETED,
            CaseModel::STATUS_DELIVERED,
        ];

        for ($i = 1; $i <= 10; $i++) {
            $status = $statuses[$i % count($statuses)];
            $clinic = $clinics[$i % $clinics->count()];
            $lab = $labs[$i % $labs->count()];
            $patient = $patients[$i % $patients->count()];
            $doctor = $doctors[$i % $doctors->count()];
            $technician = $users->where('lab_id', $lab->id)->first() ?? $users->random();
            $delivery = $users->random();
            $creator = $technician;

            $case = CaseModel::query()->create([
                'case_number' => 'CASE-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'clinic_id' => $clinic->id,
                'lab_id' => $lab->id,
                'patient_id' => $patient->id,
                'dentist_id' => $doctor->id,
                'status' => $status,
                'priority' => $i % 2 === 0 ? CaseModel::PRIORITY_URGENT : CaseModel::PRIORITY_NORMAL,
                'due_date' => now()->addDays(3 + $i),
                'case_type' => $i % 2 === 0 ? 'Crown' : 'Bridge',
                'tooth_numbers' => $i % 2 === 0 ? ['11', '12'] : ['21'],
                'description' => 'Case description for case #' . $i,
                'assigned_technician_id' => $technician?->id,
                'assigned_delivery_id' => $delivery?->id,
                'created_by' => $creator?->id,
                'completed_at' => in_array($status, [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED], true) ? now()->subDays(1) : null,
                'delivered_at' => $status === CaseModel::STATUS_DELIVERED ? now() : null,
            ]);

            if ($i % 3 === 0) {
                CaseAttachment::query()->create([
                    'case_id' => $case->id,
                    'uploaded_by' => $creator?->id,
                    'file_name' => 'impression-' . $i . '.pdf',
                    'file_path' => 'cases/attachments/demo-' . $i . '.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 204800,
                    'attachment_type' => 'impression',
                ]);
            }
        }

        $this->command->info('Cases seeded successfully.');
    }
}
