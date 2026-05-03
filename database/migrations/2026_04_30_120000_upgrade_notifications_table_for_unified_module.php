<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->json('delivery_method')->nullable()->after('priority');

            $table->foreignId('user_id')
                ->nullable()
                ->after('delivery_methods')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('role')->nullable()->after('user_id');

            $table->index(['role', 'user_id']);
        });

        //  migrate old data safely
        DB::table('notifications')
            ->select(['id', 'audience_type', 'audience_id', 'delivery_methods'])
            ->orderBy('id')
            ->chunkById(200, function ($notifications) {

                // نجيب كل user IDs مرة واحدة (performance)
                $userIds = collect($notifications)
                    ->where('audience_type', 'user')
                    ->pluck('audience_id')
                    ->filter()
                    ->unique()
                    ->values();

                $users = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('role', 'id');

                foreach ($notifications as $notification) {

                    $userId = null;


                    if ($notification->audience_type === 'user' && $notification->audience_id) {
                        if (isset($users[$notification->audience_id])) {
                            $userId = $notification->audience_id;
                        }
                    }

                    $role = match ($notification->audience_type) {
                        'clinic' => 'clinic',
                        'super-admin', 'super_admin' => 'super_admin',
                        'user' => $this->mapUserRole($users[$userId] ?? null),
                        default => 'owner',
                    };

                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update([
                            'delivery_method' => $notification->delivery_methods
                                ? json_encode($notification->delivery_methods)
                                : json_encode(['system']), // fallback

                            'user_id' => $userId,
                            'role' => $role,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['role', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['delivery_method', 'role']);
        });
    }

    private function mapUserRole(?string $role): string
    {
        return match ($role) {
            'super-admin' => 'super_admin',

            'clinic_admin',
            'doctor',
            'nurse',
            'accountant',
            'receptionist',
            'staff' => 'clinic',

            default => 'owner',
        };
    }
};
