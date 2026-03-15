<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();

            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('dentist_id')->constrained('doctors')->cascadeOnDelete();

            $table->enum('status', [
                'Pending',
                'Accepted',
                'In Progress',
                'Completed',
                'Delivered'
            ])->default('Pending');

            $table->enum('priority', ['Normal', 'Urgent'])->default('Normal');

            $table->date('due_date');
            $table->string('case_type');
            $table->json('tooth_numbers')->nullable();
            $table->text('description')->nullable();

            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_delivery_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index(['clinic_id', 'lab_id']);
            $table->index(['status', 'priority']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
