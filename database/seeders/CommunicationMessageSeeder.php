<?php

namespace Database\Seeders;

use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CommunicationMessageSeeder extends Seeder
{
    public function run(): void
    {
        $conversations = CommunicationConversation::query()->get();
        $admin = User::query()->role('super-admin')->first() ?? User::query()->first();

        if ($conversations->isEmpty()) {
            $this->command->warn('No conversations found. Seed conversations first.');
            return;
        }

        foreach ($conversations as $conversation) {
            CommunicationMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $admin?->id,
                'sender_name' => $admin?->name ?? 'Super Admin',
                'sender_type' => 'super-admin',
                'text' => 'Reminder: please update the case status when ready.',
                'type' => CommunicationMessage::TYPE_TEXT,
                'related_id' => null,
                'attachment_url' => null,
                'is_system_message' => false,
                'is_read' => false,
                'read_at' => null,
                'created_at' => Carbon::now()->subHours(4),
                'updated_at' => Carbon::now()->subHours(4),
            ]);

            $last = CommunicationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->latest('created_at')
                ->latest('id')
                ->first();

            $conversation->update([
                'last_message_text' => $last?->text,
                'last_message_at' => $last?->created_at,
                'last_message_sender_id' => $last?->sender_id,
            ]);
        }

        $this->command->info('Communication messages seeded successfully.');
    }
}
