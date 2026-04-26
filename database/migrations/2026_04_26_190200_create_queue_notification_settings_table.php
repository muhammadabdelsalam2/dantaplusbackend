<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('queue_notification_settings')) {
            return;
        }

        Schema::create('queue_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('notify_next')->default(3);
            $table->string('whatsapp_provider', 100)->default('twilio_whatsapp_api');
            $table->text('message_template')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_notification_settings');
    }
};
