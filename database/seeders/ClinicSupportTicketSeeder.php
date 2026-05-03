<?php

namespace Database\Seeders;

use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClinicSupportTicketSeeder extends Seeder
{
    public function run(): void
    {
        $clinicId = 26;
        $clinicName = 'Test Clinic';

        // Create a support ticket for clinic_id = 26
        $ticket = SupportTicket::create([
            'code' => 'ST-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
            'reporter_type' => 'clinic',
            'reporter_id' => $clinicId,
            'clinic_id' => $clinicId,
            'lab_id' => null,
            'title' => 'Inventory issue',
            'description' => 'We are experiencing issues with the inventory scanning system. Items are not being properly recorded in the system.',
            'category' => 'Technical',
            'priority' => 'High',
            'status' => 'Open',
            'assigned_to' => null,
            'last_reply_at' => null,
        ]);

        // Create a reply for the ticket
        $adminUser = User::query()->role('super-admin')->first();

        SupportReply::create([
            'support_ticket_id' => $ticket->id,
            'sender_id' => $adminUser?->id ?? 1,
            'sender_name' => $adminUser?->name ?? 'Support Agent',
            'sender_role' => 'super-admin',
            'message' => 'We are checking the inventory scanning system and will get back to you shortly.',
        ]);

        // Update last_reply_at
        $ticket->update(['last_reply_at' => now()]);

        $this->command->info("Created support ticket for clinic_id: {$clinicId}");
    }
}
