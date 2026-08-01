<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinic_communication_permissions')) {
            return;
        }

        Schema::create('clinic_communication_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('role', 50);
            $table->boolean('can_send_notes')->default(true);
            $table->boolean('can_send_voice_notes')->default(true);
            $table->boolean('can_access_patient_discussions')->default(true);
            $table->boolean('can_delete_messages')->default(false);
            $table->timestamps();

            $table->unique(['clinic_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_communication_permissions');
    }
};
