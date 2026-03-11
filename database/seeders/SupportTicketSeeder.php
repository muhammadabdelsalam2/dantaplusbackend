<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\DentalLab;
use App\Models\MaterialCompany;
use App\Models\Patient;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SupportTicketSeeder extends Seeder
{
    public function run(): void
    {
        $clinicIds = Clinic::query()->pluck('id')->all();
        $patientIds = Patient::query()->pluck('id')->all();
        $companyIds = MaterialCompany::query()->pluck('id')->all();

        if (DentalLab::query()->count() === 0) {
            DentalLab::create(['name' => 'Lab Alpha']);
            DentalLab::create(['name' => 'Lab Beta']);
        }

        $labIds = DentalLab::query()->pluck('id')->all();
        $assignee = User::query()->role('super-admin')->first() ?? User::query()->first();

        $categories = ['Billing', 'Technical', 'Account', 'Operations'];

        for ($i = 1; $i <= 15; $i++) {
            $reporterType = Arr::random(SupportTicket::REPORTER_TYPES);
            $reporterId = 1;
            $clinicId = null;
            $labId = null;

            if ($reporterType === SupportTicket::REPORTER_TYPE_CLINIC && ! empty($clinicIds)) {
                $reporterId = Arr::random($clinicIds);
                $clinicId = $reporterId;
            } elseif ($reporterType === SupportTicket::REPORTER_TYPE_LAB && ! empty($labIds)) {
                $reporterId = Arr::random($labIds);
                $labId = $reporterId;
            } elseif ($reporterType === SupportTicket::REPORTER_TYPE_PATIENT && ! empty($patientIds)) {
                $reporterId = Arr::random($patientIds);
            } elseif ($reporterType === SupportTicket::REPORTER_TYPE_COMPANY && ! empty($companyIds)) {
                $reporterId = Arr::random($companyIds);
            }

            SupportTicket::create([
                'code' => 'ST-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
                'reporter_type' => $reporterType,
                'reporter_id' => $reporterId,
                'clinic_id' => $clinicId,
                'lab_id' => $labId,
                'title' => 'Support ticket #' . $i,
                'description' => 'Demo support ticket description ' . Str::random(20),
                'category' => Arr::random($categories),
                'priority' => Arr::random(SupportTicket::PRIORITIES),
                'status' => Arr::random(SupportTicket::STATUSES),
                'assigned_to' => random_int(0, 1) ? $assignee?->id : null,
                'last_reply_at' => random_int(0, 1) ? now()->subDays(random_int(0, 7)) : null,
            ]);
        }
    }
}
