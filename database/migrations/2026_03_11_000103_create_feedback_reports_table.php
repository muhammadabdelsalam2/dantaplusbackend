<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('allow_testimonial')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('appointment_id');
            $table->index('clinic_id');
            $table->index('patient_id');
            $table->index('rating');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_reports');
    }
};
