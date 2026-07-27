<?php

namespace Database\Seeders;

use App\Models\AiAlert;
use App\Models\Clinic;
use App\Models\MaintenanceCompany;
use App\Models\OwnerMaintenanceRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EquipmentSmartAlertSeeder extends Seeder
{
    public function run(): void
    {
        $company = MaintenanceCompany::query()
            ->where('status', MaintenanceCompany::STATUS_ACTIVE)
            ->orderBy('id')
            ->first()
            ?? MaintenanceCompany::query()->orderBy('id')->first();

        $clinic = Clinic::query()
            ->where('status', 'Active')
            ->orderBy('id')
            ->first()
            ?? Clinic::query()->orderBy('id')->first();

        if (! $company || ! $clinic) {
            $this->command?->warn('EquipmentSmartAlertSeeder skipped: create at least one maintenance company and one clinic first.');

            return;
        }

        $request = OwnerMaintenanceRequest::query()->updateOrCreate(
            ['request_code' => 'MR-DEMO-DELAYED-PICKUPS'],
            [
                'clinic_id' => $clinic->id,
                'equipment' => 'X-Ray Unit',
                'issue_description' => 'Pickup has been pending for more than 48 hours.',
                'assigned_company_id' => $company->id,
                'status' => OwnerMaintenanceRequest::STATUS_PENDING,
            ]
        );

        AiAlert::query()->updateOrCreate(
            [
                'type' => 'slow_turnaround',
                'title' => 'Slow Turnaround',
                'company_id' => $company->id,
                'maintenance_request_id' => null,
            ],
            [
                'message' => "{$company->name} average response time increased by 20% this week.",
                'severity' => AiAlert::SEVERITY_HIGH,
                'is_reviewed' => false,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        AiAlert::query()->updateOrCreate(
            [
                'type' => 'delayed_pickups',
                'title' => 'Delayed Pickups',
                'company_id' => $company->id,
                'maintenance_request_id' => $request->id,
            ],
            [
                'message' => "3 requests from {$clinic->name} are pending for more than 48 hours.",
                'severity' => AiAlert::SEVERITY_MEDIUM,
                'is_reviewed' => false,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        $this->command?->info('Equipment smart alerts seeded for ' . Str::of($company->name)->limit(40) . ' and ' . Str::of($clinic->name)->limit(40) . '.');
    }
}
