<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clinic_lab_partnerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedInteger('total_cases_sent')->default(0);
            $table->timestamps();

            $table->unique(['clinic_id', 'lab_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_lab_partnerships');
    }
};
