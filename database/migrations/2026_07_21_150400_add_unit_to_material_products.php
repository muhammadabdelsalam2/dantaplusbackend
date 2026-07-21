<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('material_products', 'unit')) {
            Schema::table('material_products', function (Blueprint $table) {
                $table->string('unit', 50)->default('piece');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('material_products', 'unit')) {
            Schema::table('material_products', function (Blueprint $table) {
                $table->dropColumn('unit');
            });
        }
    }
};
