<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('patient_appointment_ratings')) {
            return;
        }

        Schema::create('patient_appointment_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('clinic_appointments')->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('doctor_rating');
            $table->unsignedTinyInteger('clinic_rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['appointment_id', 'patient_id']);
            $table->index(['clinic_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_appointment_ratings');
    }
};
