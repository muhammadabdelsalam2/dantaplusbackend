<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('feedback_logs')) {
            return;
        }

        Schema::create('feedback_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('clinic_appointment_id')->nullable()->constrained('clinic_appointments')->nullOnDelete();
            $table->string('channel', 30);
            $table->text('message_template')->nullable();
            $table->longText('rendered_message')->nullable();
            $table->text('feedback_link')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_logs');
    }
};
