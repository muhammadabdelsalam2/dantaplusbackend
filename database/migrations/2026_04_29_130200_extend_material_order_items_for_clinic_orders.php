<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('material_order_items', 'qty_original')) {
                $table->unsignedInteger('qty_original')->nullable()->after('quantity');
            }

            if (! Schema::hasColumn('material_order_items', 'qty_modified')) {
                $table->unsignedInteger('qty_modified')->nullable()->after('qty_original');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_order_items', function (Blueprint $table) {
            foreach (['qty_original', 'qty_modified'] as $column) {
                if (Schema::hasColumn('material_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
