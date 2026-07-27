<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE communication_conversations
            MODIFY status ENUM('Active', 'Resolved', 'Escalated')
            NOT NULL DEFAULT 'Active'
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE communication_conversations
            MODIFY status ENUM('Open', 'Pending', 'Resolved', 'Closed')
            NOT NULL DEFAULT 'Open'
        ");
    }
};
