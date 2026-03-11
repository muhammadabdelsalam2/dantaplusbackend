<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE owner_maintenance_requests
            MODIFY status ENUM('Pending', 'In Progress', 'Completed', 'Overdue')
            NOT NULL DEFAULT 'Pending'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE owner_maintenance_requests
            MODIFY status ENUM('Open', 'In Progress', 'Resolved', 'Closed')
            NOT NULL DEFAULT 'Open'
        ");
    }
};
