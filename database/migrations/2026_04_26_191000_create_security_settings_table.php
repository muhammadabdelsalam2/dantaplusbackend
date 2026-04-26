<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('security_settings')) {
            return;
        }

        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->boolean('enable_2fa')->default(false);
            $table->string('backup_schedule', 20)->default('daily');
            $table->unsignedInteger('retention_days')->default(3650);
            $table->timestamps();

            $table->unique('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_settings');
    }
};
