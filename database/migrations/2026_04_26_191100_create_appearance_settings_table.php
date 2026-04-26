<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('appearance_settings')) {
            return;
        }

        Schema::create('appearance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('theme', 20)->default('light');
            $table->string('primary_color', 20)->default('#4F46E5');
            $table->string('language', 20)->default('en');
            $table->timestamps();

            $table->unique('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appearance_settings');
    }
};
