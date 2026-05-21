<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_bot_settings')) {
            return;
        }

        Schema::create('whatsapp_bot_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->text('welcome_message')->nullable();
            $table->text('out_of_hours_message')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('language', ['ar', 'en', 'auto'])->default('auto');
            $table->boolean('require_deposit')->default(false);
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->json('allowed_services')->nullable();
            $table->boolean('ai_enabled')->default(false);
            $table->timestamps();

            $table->unique('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bot_settings');
    }
};
