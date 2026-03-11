<?php

namespace Database\Seeders;

use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SupportReplySeeder extends Seeder
{
    public function run(): void
    {
        $ticketIds = SupportTicket::query()->pluck('id')->all();
        $agents = User::query()->role('super-admin')->get();

        if (empty($ticketIds)) {
            return;
        }

        for ($i = 1; $i <= 20; $i++) {
            $agent = $agents->isNotEmpty() ? $agents->random() : null;
            $ticketId = Arr::random($ticketIds);

            SupportReply::create([
                'support_ticket_id' => $ticketId,
                'sender_id' => $agent?->id,
                'sender_name' => $agent?->name ?? 'Support Agent',
                'sender_role' => $agent ? 'super-admin' : 'support',
                'message' => 'Support reply #' . $i,
            ]);

            SupportTicket::query()
                ->where('id', $ticketId)
                ->update(['last_reply_at' => now()->subDays(random_int(0, 5))]);
        }
    }
}
