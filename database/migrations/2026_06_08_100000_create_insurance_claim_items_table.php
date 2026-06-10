<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('insurance_claim_items')) {
            return;
        }

        Schema::create('insurance_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $table->foreignId('insurance_price_list_item_id')->nullable()->constrained('insurance_price_list_items')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('service_name');
            $table->foreignId('category_id')->nullable();
            $table->string('category_name')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total_amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['insurance_claim_id']);
            $table->index(['insurance_price_list_item_id']);
            $table->index(['service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_items');
    }
};
