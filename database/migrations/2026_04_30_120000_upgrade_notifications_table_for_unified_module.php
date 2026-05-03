<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //  Add columns safely
        Schema::table('notifications', function (Blueprint $table) {

            if (!Schema::hasColumn('notifications', 'delivery_method')) {
                $table->json('delivery_method')->nullable()->after('priority');
            }

            if (!Schema::hasColumn('notifications', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('delivery_methods')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('notifications', 'role')) {
                $table->string('role')->nullable()->after('user_id');
            }
        });

        //  Add index safely
        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['role', 'user_id']);
            });
        } catch (\Throwable $e) {
            // ignore لو موجود بالفعل
        }

        // migrate old data safely
        DB::table('notifications')
            ->select(['id', 'audience_type', 'audience_id', 'delivery_methods'])
            ->orderBy('id')
            ->chunkById(200, function ($notifications) {

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

                    //  check valid user
                    if (
                        $notification->audience_type === 'user' &&
                        $notification->audience_id &&
                        isset($users[$notification->audience_id])
                    ) {
                        $userId = $notification->audience_id;
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
                                : json_encode(['system']),

                            'user_id' => $userId,
                            'role' => $role,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {

            if (Schema::hasColumn('notifications', 'user_id')) {
                try {
                    $table->dropConstrainedForeignId('user_id');
                } catch (\Throwable $e) {}
            }

            if (Schema::hasColumn('notifications', 'delivery_method')) {
                $table->dropColumn('delivery_method');
            }

            if (Schema::hasColumn('notifications', 'role')) {
                $table->dropColumn('role');
            }

            try {
                $table->dropIndex(['role', 'user_id']);
            } catch (\Throwable $e) {}
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
