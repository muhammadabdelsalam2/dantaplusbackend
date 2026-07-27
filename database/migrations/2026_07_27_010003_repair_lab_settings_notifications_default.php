<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql'
            || ! Schema::hasTable('lab_settings')
            || ! Schema::hasColumn('lab_settings', 'notifications_json')) {
            return;
        }

        DB::statement('
            ALTER TABLE lab_settings
            MODIFY notifications_json JSON NOT NULL DEFAULT (JSON_OBJECT(
                "new_case_alerts", JSON_OBJECT("in_app_notification", true, "email_notification", false),
                "case_update_alerts", JSON_OBJECT("in_app_notification", true, "email_notification", false)
            ))
        ');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql'
            || ! Schema::hasTable('lab_settings')
            || ! Schema::hasColumn('lab_settings', 'notifications_json')) {
            return;
        }

        DB::statement('ALTER TABLE lab_settings ALTER notifications_json DROP DEFAULT');
    }
};
