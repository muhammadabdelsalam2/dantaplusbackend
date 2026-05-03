<?php

use App\Models\ProcurementOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('material_products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('material_companies')->nullOnDelete();
            $table->string('supplier_name');
            $table->unsignedInteger('qty');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('status', 30)->default(ProcurementOrder::STATUS_PENDING);
            $table->string('po_number')->unique();
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_orders');
    }
};
