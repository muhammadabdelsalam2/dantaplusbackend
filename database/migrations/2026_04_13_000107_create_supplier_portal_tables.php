<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('material_companies')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('material_products')->nullOnDelete();
            $table->string('product_name');
            $table->string('category_name')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('minimum_stock_level')->default(0);
            $table->string('unit', 50)->default('piece');
            $table->string('supplier')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'quantity']);
        });

        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('material_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->integer('amount');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'action']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('material_orders')->nullOnDelete();
            $table->foreignId('company_id')->constrained('material_companies')->cascadeOnDelete();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('payment_method')->nullable();
            $table->timestamp('completion_date')->nullable();
            $table->string('order_type')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('material_companies')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 50)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('company_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('material_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'expense_date']);
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('material_companies')->cascadeOnDelete();
            $table->string('transaction_id')->unique();
            $table->date('transaction_date');
            $table->decimal('amount', 12, 2);
            $table->string('source')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('type', 30)->default('credit');
            $table->foreignId('matched_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'transaction_date']);
        });

        Schema::create('shared_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('communication_conversations')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('material_companies')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->string('file_path');
            $table->string('uploaded_by_type')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_by_name')->nullable();
            $table->foreignId('related_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('material_companies')->cascadeOnDelete();
            $table->string('zone_name');
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->string('estimated_delivery_time')->nullable();
            $table->json('polygon_coordinates')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('material_companies')->cascadeOnDelete();
            $table->json('profile')->nullable();
            $table->json('communication')->nullable();
            $table->json('automation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shared_files');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('company_expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('inventory_items');
    }
};
