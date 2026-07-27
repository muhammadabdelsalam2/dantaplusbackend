<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || ! Schema::hasTable('owner_maintenance_requests')) {
            return;
        }

        DB::statement("
            ALTER TABLE owner_maintenance_requests
            MODIFY status ENUM('Pending', 'In Progress', 'Completed', 'Overdue')
            NOT NULL DEFAULT 'Pending'
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || ! Schema::hasTable('owner_maintenance_requests')) {
            return;
        }

        DB::statement("
            ALTER TABLE owner_maintenance_requests
            MODIFY status ENUM('Open', 'In Progress', 'Resolved', 'Closed')
            NOT NULL DEFAULT 'Open'
        ");
    }
};
