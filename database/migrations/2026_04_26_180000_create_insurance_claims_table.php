<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('insurance_claims')) {
            return;
        }

        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('insurance_company_id')->constrained('insurance_companies')->restrictOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('clinic_appointments')->nullOnDelete();
            $table->foreignId('clinic_invoice_id')->nullable()->constrained('clinic_invoices')->nullOnDelete();
            $table->string('claim_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('service_date');
            $table->decimal('coverage_percentage', 5, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('patient_share_amount', 12, 2)->default(0);
            $table->decimal('insurance_share_amount', 12, 2)->default(0);
            $table->decimal('approved_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 50)->default('draft');
            $table->text('notes')->nullable();
            $table->text('status_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'patient_id']);
            $table->index(['clinic_id', 'insurance_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
    }
};
