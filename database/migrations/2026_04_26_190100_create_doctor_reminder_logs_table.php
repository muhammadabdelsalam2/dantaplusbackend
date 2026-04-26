<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('doctor_reminder_logs')) {
            return;
        }

        Schema::create('doctor_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 30);
            $table->text('message_template')->nullable();
            $table->longText('rendered_message')->nullable();
            $table->date('reminder_date');
            $table->string('status', 30)->default('sent');
            $table->timestamp('triggered_at')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'reminder_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_reminder_logs');
    }
};
