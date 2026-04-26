<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('clinic_service_prices')) {
            return;
        }

        Schema::create('clinic_service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('lab_cost', 10, 2)->default(0);
            $table->boolean('has_lab')->default(false);
            $table->timestamps();

            $table->unique(['clinic_id', 'service_id']);
            $table->index(['clinic_id', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_service_prices');
    }
};
