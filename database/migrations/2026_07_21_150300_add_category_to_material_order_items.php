<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('material_order_items', 'category')) {
            Schema::table('material_order_items', function (Blueprint $table) {
                $table->string('category')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('material_order_items', 'category')) {
            Schema::table('material_order_items', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
