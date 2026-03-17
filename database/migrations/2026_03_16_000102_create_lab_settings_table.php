<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_settings', function (Blueprint $table) {
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete()->primary();
            $table->json('notifications_json')->default('{"new_case_alerts":{"in_app_notification":true,"email_notification":false},"case_update_alerts":{"in_app_notification":true,"email_notification":false}}');
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
