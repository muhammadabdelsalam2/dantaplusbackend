<?php

namespace Database\Seeders;

use App\Models\CaseMessage;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CaseMessageSeeder extends Seeder
{
    public function run(): void
    {
        $cases = CaseModel::query()->with(['clinic', 'lab'])->get();
        $users = User::query()->get();

        if ($cases->isEmpty()) {
            $this->command->warn('No cases found. Seed cases first.');
            return;
        }

        foreach ($cases as $index => $case) {
            $senderLab = $users->where('lab_id', $case->lab_id)->first();

            CaseMessage::query()->create([
                'case_id' => $case->id,
                'sender_id' => $senderLab?->id,
                'sender_name' => $senderLab?->name ?? $case->lab?->name,
                'sender_type' => 'lab',
                'message' => 'Case received and under review.',
                'is_internal' => false,
                'is_read' => true,
                'read_at' => now()->subDays(1),
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ]);

            CaseMessage::query()->create([
                'case_id' => $case->id,
                'sender_id' => null,
                'sender_name' => $case->clinic?->name ?? 'Clinic',
                'sender_type' => 'clinic',
                'message' => 'Please prioritize this case due to patient needs.',
                'is_internal' => false,
                'is_read' => $index % 2 === 0,
                'read_at' => $index % 2 === 0 ? now()->subHours(2) : null,
                'created_at' => Carbon::now()->subHours(5),
                'updated_at' => Carbon::now()->subHours(5),
            ]);

            if ($index % 3 === 0) {
                CaseMessage::query()->create([
                    'case_id' => $case->id,
                    'sender_id' => $senderLab?->id,
                    'sender_name' => $senderLab?->name ?? 'Lab',
                    'sender_type' => 'lab',
                    'message' => 'Internal note: waiting for materials.',
                    'is_internal' => true,
                    'is_read' => false,
                    'read_at' => null,
                    'created_at' => Carbon::now()->subHours(1),
                    'updated_at' => Carbon::now()->subHours(1),
                ]);
            }
        }

        $this->command->info('Case messages seeded successfully.');
    }
}
