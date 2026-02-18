<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();

            // // Basic Info
            // $table->string('first_name');
            // $table->string('last_name')->nullable();
            // $table->string('email')->nullable()->unique();
            // $table->string('phone')->nullable();
            // $table->date('dob')->nullable(); // Date of Birth
            // $table->enum('gender', ['male', 'female', 'other'])->nullable();

            // // Identification
            // $table->string('national_id')->nullable()->unique();
            // $table->string('insurance_number')->nullable();
            // $table->string('passport_number')->nullable();

            // // Address
            // $table->string('address')->nullable();
            // $table->string('city')->nullable();
            // $table->string('state')->nullable();
            // $table->string('zip_code')->nullable();
            // $table->string('country')->nullable();

            // // Clinic & Assignment
            // $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            // $table->foreignId('assigned_employee_id')->nullable()->constrained('users')->nullOnDelete();

            // // Medical Info
            // $table->text('medical_history')->nullable();
            // $table->text('allergies')->nullable();
            // $table->text('current_medication')->nullable();
            // $table->text('notes')->nullable();

            // // Status & Flags
            // $table->boolean('active')->default(true);
            // $table->date('last_visit')->nullable();
            // $table->string('blood_type')->nullable();
            // $table->string('emergency_contact_name')->nullable();
            // $table->string('emergency_contact_phone')->nullable();
            // $table->string('avatar')->nullable(); // profile picture
            // // Timestamps
            // $table->timestamps();
            $table->softDeletes(); // allows soft deleting
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
