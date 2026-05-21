<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_messages')) {
            return;
        }

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('patient_phone', 50);
            $table->text('message');
            $table->text('reply')->nullable();
            $table->string('intent', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['clinic_id', 'patient_phone']);
            $table->index('intent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
