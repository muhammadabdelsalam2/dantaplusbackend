<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================
        // 1️⃣ message_templates
        // =========================
        if (!Schema::hasTable('message_templates')) {
            Schema::create('message_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->enum('message_type', ['confirmation', 'reminder', 'follow_up', 'custom']);
                $table->enum('channel', ['sms', 'whatsapp']);
                $table->text('body');
                $table->json('placeholders')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['clinic_id', 'is_active']);
            });
        }

        // =========================
        // 2️⃣ messages
        // =========================
        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->unsignedBigInteger('template_id')->nullable();

                $table->enum('channel', ['sms', 'whatsapp']);
                $table->enum('message_type', ['confirmation', 'reminder', 'follow_up', 'custom']);
                $table->text('message');
                $table->uuid('batch_uuid')->index();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['clinic_id', 'sent_at']);
            });
        }

        // إضافة FK لو مش موجود
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'template_id')) {
            Schema::table('messages', function (Blueprint $table) {
                try {
                    $table->foreign('template_id')
                        ->references('id')
                        ->on('message_templates')
                        ->nullOnDelete();
                } catch (\Exception $e) {
                    // لو معمول قبل كدا تجاهل
                }
            });
        }

        // =========================
        // 3️⃣ message_logs
        // =========================
        if (!Schema::hasTable('message_logs')) {
            Schema::create('message_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
                $table->foreignId('appointment_id')->nullable()->constrained('clinic_appointments')->nullOnDelete();
                $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();

                $table->uuid('batch_uuid')->index();
                $table->enum('channel', ['sms', 'whatsapp']);
                $table->enum('message_type', ['confirmation', 'reminder', 'follow_up', 'custom']);
                $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
                $table->text('message_body');
                $table->string('phone', 30)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['clinic_id', 'patient_id']);
                $table->index(['clinic_id', 'status']);
                $table->index(['batch_uuid', 'status']);
            });
        }

        // FK لـ message_logs.template_id
        if (Schema::hasTable('message_logs') && Schema::hasColumn('message_logs', 'template_id')) {
            Schema::table('message_logs', function (Blueprint $table) {
                try {
                    $table->foreign('template_id')
                        ->references('id')
                        ->on('message_templates')
                        ->nullOnDelete();
                } catch (\Exception $e) {
                    // تجاهل لو موجود
                }
            });
        }
    }

    public function down(): void
    {
        // في production منمسحش الداتا
        // سيبها فاضية أو امسح FK بس لو عايز
    }
};
