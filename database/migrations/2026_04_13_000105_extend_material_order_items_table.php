<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('material_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('material_order_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('material_order_items', 'unit')) {
                $table->string('unit', 50)->nullable()->after('item_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_order_items', function (Blueprint $table) {
            foreach (['item_name', 'unit'] as $column) {
                if (Schema::hasColumn('material_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
