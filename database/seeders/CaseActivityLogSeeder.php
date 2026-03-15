<?php

namespace Database\Seeders;

use App\Models\CaseActivityLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CaseActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $cases = CaseModel::query()->get();
        $users = User::query()->get();

        if ($cases->isEmpty()) {
            $this->command->warn('No cases found. Seed cases first.');
            return;
        }

        foreach ($cases as $case) {
            $actor = $users->where('lab_id', $case->lab_id)->first() ?? $users->first();

            CaseActivityLog::query()->create([
                'case_id' => $case->id,
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'action' => 'case_created',
                'new_status' => $case->status,
                'notes' => 'Case created via seeder',
                'payload' => ['case_number' => $case->case_number],
                'created_at' => Carbon::now()->subDays(3),
            ]);

            CaseActivityLog::query()->create([
                'case_id' => $case->id,
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'action' => 'status_changed',
                'old_status' => CaseModel::STATUS_PENDING,
                'new_status' => $case->status,
                'notes' => 'Status updated in seed data',
                'payload' => null,
                'created_at' => Carbon::now()->subDays(1),
            ]);

            if ($case->assigned_technician_id) {
                CaseActivityLog::query()->create([
                    'case_id' => $case->id,
                    'actor_id' => $actor?->id,
                    'actor_name' => $actor?->name,
                    'action' => 'technician_assigned',
                    'old_status' => $case->status,
                    'new_status' => $case->status,
                    'notes' => 'Technician assigned',
                    'payload' => ['assigned_technician_id' => $case->assigned_technician_id],
                    'created_at' => Carbon::now()->subHours(5),
                ]);
            }
        }

        $this->command->info('Case activity logs seeded successfully.');
    }
}
