<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('doctor_reminder_settings')) {
            return;
        }

        Schema::create('doctor_reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->time('send_time')->default('20:00:00');
            $table->json('channels')->nullable();
            $table->text('message_template')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_reminder_settings');
    }
};
