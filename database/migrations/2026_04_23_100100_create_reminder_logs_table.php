<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('reminder_logs')) {
            return;
        }

        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('clinic_appointment_id')->nullable()->constrained('clinic_appointments')->nullOnDelete();
            $table->string('channel', 30)->default('whatsapp');
            $table->text('template')->nullable();
            $table->string('status', 30)->default('simulated');
            $table->timestamp('triggered_at');
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'triggered_at']);
            $table->index(['clinic_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
