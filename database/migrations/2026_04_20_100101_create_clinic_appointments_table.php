<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clinic_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('patient_name');
            $table->string('patient_phone', 50)->nullable();
            $table->string('service_name');
            $table->dateTime('appointment_at');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->string('branch')->nullable();
            $table->string('room')->nullable();
            $table->string('payment_type', 30)->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'appointment_at']);
            $table->index(['clinic_id', 'doctor_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_appointments');
    }
};
