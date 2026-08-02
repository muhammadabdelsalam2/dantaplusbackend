<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::table('users')
                ->whereNull('email')
                ->update(['email' => DB::raw("CONCAT('user-', id, '@placeholder.local')")]);

            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
        }
    }
};
