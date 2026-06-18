<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['lab_id', 'name']);
            $table->index(['lab_id', 'status']);
        });

        Schema::create('lab_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('period_month')->nullable();
            $table->string('group_by', 30)->default('manual');
            $table->string('group_key', 80)->default('manual');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['lab_id', 'clinic_id', 'period_month', 'group_by', 'group_key'], 'lab_invoice_period_unique');
            $table->index(['lab_id', 'status']);
            $table->index(['lab_id', 'issue_date']);
            $table->index(['lab_id', 'due_date']);
        });

        Schema::create('lab_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_invoice_id')->constrained('lab_invoices')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('lab_service_id')->nullable()->constrained('lab_services')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('case_number')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('service_name');
            $table->json('teeth_numbers')->nullable();
            $table->json('fdi_teeth_numbers')->nullable();
            $table->json('dental_chart')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('materials_cost', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['lab_invoice_id', 'case_id']);
            $table->index('technician_id');
        });

        Schema::create('lab_invoice_item_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_invoice_item_id')->constrained('lab_invoice_items')->cascadeOnDelete();
            $table->foreignId('lab_material_id')->nullable()->constrained('lab_materials')->nullOnDelete();
            $table->string('material_name');
            $table->string('material_type')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->index('material_type');
        });

        Schema::create('lab_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_invoice_id')->constrained('lab_invoices')->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 50);
            $table->string('status', 30)->default('paid');
            $table->string('transaction_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['lab_id', 'paid_at']);
            $table->index(['lab_invoice_id', 'status']);
        });

        Schema::create('lab_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_payment_id')->nullable()->constrained('lab_payments')->nullOnDelete();
            $table->foreignId('lab_invoice_id')->nullable()->constrained('lab_invoices')->nullOnDelete();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('transaction_reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('pending');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['lab_id', 'provider', 'status']);
        });

        Schema::create('lab_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->foreignId('lab_expense_category_id')->nullable()->constrained('lab_expense_categories')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50)->nullable();
            $table->date('expense_date');
            $table->string('vendor')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['lab_id', 'expense_date']);
            $table->index('lab_expense_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_expenses');
        Schema::dropIfExists('lab_payment_transactions');
        Schema::dropIfExists('lab_payments');
        Schema::dropIfExists('lab_invoice_item_materials');
        Schema::dropIfExists('lab_invoice_items');
        Schema::dropIfExists('lab_invoices');
        Schema::dropIfExists('lab_expense_categories');
    }
};
