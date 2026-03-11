<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE communication_conversations
            MODIFY status ENUM('Active', 'Resolved', 'Escalated')
            NOT NULL DEFAULT 'Active'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE communication_conversations
            MODIFY status ENUM('Open', 'Pending', 'Resolved', 'Closed')
            NOT NULL DEFAULT 'Open'
        ");
    }
};
