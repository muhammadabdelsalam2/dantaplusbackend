<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TeamChatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        DB::transaction(function () {
            $clinic = Clinic::where('name', 'moamen')->first();

            if (!$clinic) {
                $this->command->warn('Clinic Not Found');

            }
            $this->command->info('This users In This Clinic Is' . $clinic->users);

            //  1. Create Users
            $users = User::factory()
                ->count(6)
                ->create([
                    'clinic_id' => $clinic->id,
                ]);
            $this->command->info('Users created for clinic ID: ' . $clinic->id);

            //  2. Assign Roles (IMPORTANT)
            $roles = ['Admin', 'doctor', 'nurse', 'receptionist', 'accountant', 'staff'];

            foreach ($users as $index => $user) {
                $user->assignRole($roles[$index % count($roles)]);
            }

            // 🏢 3. Create Team
            $teamId = DB::table('teams')->insertGetId([
                'name' => 'Dev Team',
                'owner_id' => $users->first()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 👥 4. Attach Users to Team
            foreach ($users as $user) {
                DB::table('team_users')->insert([
                    'team_id' => $teamId,
                    'user_id' => $user->id,
                    'role' => $user->hasRole('Admin') ? 'admin' : 'member',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 💬 5. Create Chat
            $chatId = DB::table('chats')->insertGetId([
                'owner_id' => $users->first()->id,
                'type' => 'group',
                'team_id' => $teamId,
                'name' => 'General',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 👤 6. Participants
            foreach ($users as $user) {
                DB::table('chat_participants')->insert([
                    'chat_id' => $chatId,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 💌 7. Messages
            $messageIds = [];

            foreach (range(1, 15) as $i) {
                $sender = $users->random();

                $messageIds[] = DB::table('message_chats')->insertGetId([
                    'chat_id' => $chatId,
                    'sender_id' => $sender->id,
                    'message' => "{$sender->getRoleNames()->first()} says message {$i}",
                    'type' => 'text',
                    'metadata' => json_encode([
                        'uuid' => Str::uuid(),
                        'role' => $sender->getRoleNames()->first(),
                    ]),
                    'created_at' => now()->subMinutes(20 - $i),
                    'updated_at' => now(),
                ]);
            }

            // 🔁 8. Replies
            foreach (range(1, 3) as $i) {
                $sender = $users->random();

                DB::table('message_chats')->insert([
                    'chat_id' => $chatId,
                    'sender_id' => $sender->id,
                    'message' => "Reply {$i} from {$sender->getRoleNames()->first()}",
                    'reply_to_id' => $messageIds[array_rand($messageIds)],
                    'type' => 'text',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 👁️ 9. Read Status
            $messages = DB::table('message_chats')->get();

            foreach ($messages as $msg) {
                foreach ($users as $user) {
                    if ($user->id != $msg->sender_id) {
                        DB::table('message_reads')->insert([
                            'message_id' => $msg->id,
                            'user_id' => $user->id,
                            'read_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // 🔔 10. Mentions
            DB::table('message_mentions')->insert([
                'message_id' => $messageIds[0],
                'user_id' => $users->where(fn($u) => $u->hasRole('doctor'))->first()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 📎 11. Attachments
            DB::table('message_attachments')->insert([
                'message_id' => $messageIds[1],
                'file_name' => 'report.pdf',
                'file_path' => 'chat/report.pdf',
                'file_type' => 'application/pdf',
                'file_size' => 5000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        });
    }
}
