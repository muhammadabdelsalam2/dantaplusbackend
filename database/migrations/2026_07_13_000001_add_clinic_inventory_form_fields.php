<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'consumption_per_case')) {
                $table->decimal('consumption_per_case', 10, 2)->nullable()->after('unit');
            }

            if (! Schema::hasColumn('inventory_items', 'auto_purchase')) {
                $table->boolean('auto_purchase')->default(false)->after('reorder_quantity');
            }

            if (! Schema::hasColumn('inventory_items', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->nullable()->after('supplier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'unit_price')) {
                $table->dropColumn('unit_price');
            }

            if (Schema::hasColumn('inventory_items', 'auto_purchase')) {
                $table->dropColumn('auto_purchase');
            }

            if (Schema::hasColumn('inventory_items', 'consumption_per_case')) {
                $table->dropColumn('consumption_per_case');
            }
        });
    }
};
