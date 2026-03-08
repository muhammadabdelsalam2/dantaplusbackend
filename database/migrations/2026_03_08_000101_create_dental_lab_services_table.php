<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dental_lab_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('turnaround_days')->nullable();
            $table->timestamps();

            $table->index(['lab_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_lab_services');
    }
};
