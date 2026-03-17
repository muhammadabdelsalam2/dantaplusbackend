<?php

namespace Database\Factories;

use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CaseFactory extends Factory
{
    protected $model = CaseModel::class;

    public function definition(): array
    {
        $clinicId = Clinic::query()->inRandomOrder()->value('id') ?? Clinic::factory();
        $labId = DentalLab::query()->inRandomOrder()->value('id') ?? DentalLab::query()->create([
            'name' => 'Factory Lab',
            'status' => 'Active',
        ])->id;

        $patientId = Patient::query()->inRandomOrder()->value('id');
        if (! $patientId) {
            $patientUser = User::factory()->create();
            $patientId = Patient::query()->create([
                'user_id' => $patientUser->id,
                'date_of_birth' => now()->subYears(25)->toDateString(),
            ])->id;
        }

        $doctorId = Doctor::query()->inRandomOrder()->value('id');
        if (! $doctorId) {
            $doctorUser = User::factory()->create();
            $doctorId = Doctor::query()->create([
                'user_id' => $doctorUser->id,
                'specialty' => 'Dentistry',
            ])->id;
        }

        return [
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'clinic_id' => $clinicId,
            'lab_id' => $labId,
            'patient_id' => $patientId,
            'dentist_id' => $doctorId,
            'status' => CaseModel::STATUS_PENDING,
            'priority' => CaseModel::PRIORITY_NORMAL,
            'due_date' => now()->addDays(7),
            'case_type' => 'Crown',
            'tooth_numbers' => ['11', '12'],
            'description' => $this->faker->sentence,
        ];
    }
}
