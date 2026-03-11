<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\DentalLab;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CommunicationConversationSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = Clinic::query()->take(5)->get();
        $labs = DentalLab::query()->take(5)->get();
        $admin = User::query()->first();

        if ($clinics->isEmpty() || $labs->isEmpty()) {
            $this->command->warn('No clinics or labs found. Seed clinics and labs first.');
            return;
        }

        foreach ($clinics as $index => $clinic) {
            $lab = $labs[$index % $labs->count()];

            $conversation = CommunicationConversation::query()->create([
                'clinic_id' => $clinic->id,
                'lab_id' => $lab->id,
                'status' => CommunicationConversation::STATUS_ACTIVE,
                'last_message_text' => null,
                'last_message_at' => null,
                'last_message_sender_id' => null,
                'created_at' => now()->subDays(rand(1, 10)),
                'updated_at' => now(),
            ]);

            $messages = [
                [
                    'conversation_id' => $conversation->id,
                    'sender_id' => null,
                    'sender_name' => $clinic->name,
                    'sender_type' => 'clinic',
                    'text' => 'Hello, we need an update regarding the case delivery.',
                    'type' => 'text',
                    'related_id' => null,
                    'attachment_url' => null,
                    'is_system_message' => false,
                    'is_read' => true,
                    'read_at' => now()->subDays(2),
                    'created_at' => Carbon::now()->subDays(2)->subHours(4),
                    'updated_at' => Carbon::now()->subDays(2)->subHours(4),
                ],
                [
                    'conversation_id' => $conversation->id,
                    'sender_id' => null,
                    'sender_name' => $lab->name,
                    'sender_type' => 'lab',
                    'text' => 'The case is under preparation and will be ready tomorrow.',
                    'type' => 'text',
                    'related_id' => null,
                    'attachment_url' => null,
                    'is_system_message' => false,
                    'is_read' => true,
                    'read_at' => now()->subDays(2),
                    'created_at' => Carbon::now()->subDays(2)->subHours(2),
                    'updated_at' => Carbon::now()->subDays(2)->subHours(2),
                ],
                [
                    'conversation_id' => $conversation->id,
                    'sender_id' => $admin?->id,
                    'sender_name' => $admin?->name ?? 'Super Admin',
                    'sender_type' => 'super-admin',
                    'text' => 'Please keep both parties updated until closure.',
                    'type' => 'text',
                    'related_id' => null,
                    'attachment_url' => null,
                    'is_system_message' => false,
                    'is_read' => true,
                    'read_at' => now()->subDay(),
                    'created_at' => Carbon::now()->subDay()->subHours(6),
                    'updated_at' => Carbon::now()->subDay()->subHours(6),
                ],
                [
                    'conversation_id' => $conversation->id,
                    'sender_id' => null,
                    'sender_name' => $clinic->name,
                    'sender_type' => 'clinic',
                    'text' => 'Thanks, waiting for confirmation once shipped.',
                    'type' => 'text',
                    'related_id' => null,
                    'attachment_url' => null,
                    'is_system_message' => false,
                    'is_read' => false,
                    'read_at' => null,
                    'created_at' => Carbon::now()->subHours(rand(2, 12)),
                    'updated_at' => Carbon::now()->subHours(rand(2, 12)),
                ],
            ];

            foreach ($messages as $messageData) {
                CommunicationMessage::query()->create($messageData);
            }

            $lastMessage = CommunicationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->latest('created_at')
                ->latest('id')
                ->first();

            $conversation->update([
                'last_message_text' => $lastMessage?->text,
                'last_message_at' => $lastMessage?->created_at,
                'last_message_sender_id' => $lastMessage?->sender_id,
            ]);
        }

        $this->command->info('Communication conversations seeded successfully.');
    }
}
