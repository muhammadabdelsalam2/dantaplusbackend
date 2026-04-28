<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clinic_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assign_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assign_to_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('todo');
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_tasks');
    }
};
