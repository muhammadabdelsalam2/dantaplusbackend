<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\FeedbackReport;
use App\Models\Patient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class FeedbackReportSeeder extends Seeder
{
    public function run(): void
    {
        $clinicIds = Clinic::query()->pluck('id')->all();
        $patientIds = Patient::query()->pluck('id')->all();

        if (empty($clinicIds) || empty($patientIds)) {
            return;
        }

        for ($i = 1; $i <= 20; $i++) {
            FeedbackReport::create([
                'appointment_id' => null,
                'clinic_id' => Arr::random($clinicIds),
                'patient_id' => Arr::random($patientIds),
                'rating' => random_int(1, 5),
                'comment' => random_int(0, 1) ? 'Great service and friendly staff.' : null,
                'allow_testimonial' => (bool) random_int(0, 1),
                'submitted_at' => now()->subDays(random_int(0, 30)),
            ]);
        }
    }
}
