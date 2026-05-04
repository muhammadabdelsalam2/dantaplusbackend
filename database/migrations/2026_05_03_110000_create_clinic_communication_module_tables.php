<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_conversations', 'contact_type')) {
                $table->string('contact_type', 30)->nullable()->after('company_id');
            }

            if (! Schema::hasColumn('communication_conversations', 'contact_id')) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('contact_type');
            }
        });

        Schema::table('communication_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_messages', 'attachment_name')) {
                $table->string('attachment_name')->nullable()->after('attachment_path');
            }
        });

        if (! Schema::hasTable('clinic_communication_settings')) {
            Schema::create('clinic_communication_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->string('whatsapp_provider')->default('meta_cloud_api');
                $table->string('whatsapp_phone_number_id')->nullable();
                $table->string('whatsapp_business_account_id')->nullable();
                $table->text('whatsapp_access_token')->nullable();
                $table->string('whatsapp_app_id')->nullable();
                $table->text('whatsapp_app_secret')->nullable();
                $table->string('whatsapp_webhook_verify_token')->nullable();
                $table->text('sms_api_key')->nullable();
                $table->string('sms_sender_name')->nullable();
                $table->string('smtp_host')->nullable();
                $table->unsignedInteger('smtp_port')->nullable();
                $table->string('smtp_username')->nullable();
                $table->text('smtp_password')->nullable();
                $table->enum('smtp_encryption', ['tls', 'ssl', 'none'])->nullable();
                $table->string('smtp_from_name')->nullable();
                $table->string('smtp_from_email')->nullable();
                $table->timestamps();

                $table->unique('clinic_id');
                $table->index(['sms_sender_name', 'smtp_from_email'], 'clinic_comm_settings_sender_email_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('communication_messages', function (Blueprint $table) {
            if (Schema::hasColumn('communication_messages', 'attachment_name')) {
                $table->dropColumn('attachment_name');
            }
        });

        Schema::table('communication_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('communication_conversations', 'contact_id')) {
                $table->dropColumn('contact_id');
            }

            if (Schema::hasColumn('communication_conversations', 'contact_type')) {
                $table->dropColumn('contact_type');
            }
        });

        Schema::dropIfExists('clinic_communication_settings');
    }
};
