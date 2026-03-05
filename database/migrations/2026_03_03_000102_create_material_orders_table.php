<?php

use App\Models\MaterialOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('material_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->foreignId('clinic_id')->constrained('clinics');
            $table->foreignId('supplier_company_id')->constrained('material_companies');
            $table->timestamp('order_date')->nullable();
            $table->decimal('amount_total', 12, 2);
            $table->enum('status', MaterialOrder::STATUSES)->default(MaterialOrder::STATUS_PENDING);
            $table->decimal('commission_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->index(['order_code', 'status', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_orders');
    }
};
