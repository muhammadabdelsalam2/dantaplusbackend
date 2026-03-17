<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->string('service_name', 255);
            $table->decimal('price', 10, 2);
            $table->unsignedTinyInteger('turnaround_time_days');
            $table->timestamps();

            $table->index('lab_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_services');
    }
};
