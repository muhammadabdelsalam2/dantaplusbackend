<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'barcode')) {
                $table->string('barcode')->nullable()->after('product_id');
            }

            if (! Schema::hasColumn('inventory_items', 'reorder_quantity')) {
                $table->integer('reorder_quantity')->default(0)->after('minimum_stock_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'barcode')) {
                $table->dropColumn('barcode');
            }

            if (Schema::hasColumn('inventory_items', 'reorder_quantity')) {
                $table->dropColumn('reorder_quantity');
            }
        });
    }
};
