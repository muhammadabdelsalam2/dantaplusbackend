<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_settings', function (Blueprint $table) {
            $defaultNotifications = Schema::getConnection()->getDriverName() === 'mysql'
                ? DB::raw('(JSON_OBJECT(
          "new_case_alerts", JSON_OBJECT("in_app_notification", true, "email_notification", false),
          "case_update_alerts", JSON_OBJECT("in_app_notification", true, "email_notification", false)
      ))')
                : json_encode([
                    'new_case_alerts' => ['in_app_notification' => true, 'email_notification' => false],
                    'case_update_alerts' => ['in_app_notification' => true, 'email_notification' => false],
                ]);

            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete()->primary();
            $table->json('notifications_json')
                ->default($defaultNotifications);
            $table->string('whatsapp_provider', 30)->nullable();
            $table->text('whatsapp_meta_json')->nullable();
            $table->text('whatsapp_twilio_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_settings');
    }
};
