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
        if (! Schema::hasTable('material_orders')) {
            return;
        }

        $hasClinicId = Schema::hasColumn('material_orders', 'clinic_id');
        $hasShippingCost = Schema::hasColumn('material_orders', 'shipping_cost');

        Schema::table('material_orders', function (Blueprint $table) {
            if (Schema::hasColumn('material_orders', 'shipping_fee') && ! Schema::hasColumn('material_orders', 'shipping_cost')) {
                $table->renameColumn('shipping_fee', 'shipping_cost');
            } elseif (Schema::hasColumn('material_orders', 'shipping_price') && ! Schema::hasColumn('material_orders', 'shipping_cost')) {
                $table->renameColumn('shipping_price', 'shipping_cost');
            } elseif (Schema::hasColumn('material_orders', 'shipping') && ! Schema::hasColumn('material_orders', 'shipping_cost')) {
                $table->renameColumn('shipping', 'shipping_cost');
            }
        });

        Schema::table('material_orders', function (Blueprint $table) use ($hasClinicId, $hasShippingCost) {
            if ($hasClinicId) {
                $table->unsignedBigInteger('clinic_id')->nullable()->change();
            } else {
                $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            }

            if ($hasShippingCost || Schema::hasColumn('material_orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)->nullable()->default(0)->change();
            } else {
                $table->decimal('shipping_cost', 10, 2)->nullable()->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
