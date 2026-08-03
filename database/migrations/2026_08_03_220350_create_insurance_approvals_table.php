<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('insurance_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('insurance_company_id')->constrained('insurance_companies')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('approval_number')->nullable();
            $table->string('ref_id')->nullable();
            $table->string('status', 30)->default('Pending');
            $table->date('date');
            $table->date('expiry_date')->nullable();
            $table->decimal('coverage_percent', 5, 2)->default(0);
            $table->decimal('approved_amount', 12, 2)->default(0);
            $table->decimal('used_amount', 12, 2)->default(0);
            $table->json('documents')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'patient_id', 'status']);
            $table->index(['insurance_company_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_approvals');
    }
};
