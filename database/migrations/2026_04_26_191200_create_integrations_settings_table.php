<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('integrations_settings')) {
            return;
        }

        Schema::create('integrations_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->boolean('connected')->default(false);
            $table->timestamps();

            $table->unique(['clinic_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_settings');
    }
};
