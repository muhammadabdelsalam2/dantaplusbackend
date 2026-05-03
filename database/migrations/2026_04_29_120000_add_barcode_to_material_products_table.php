<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('material_products', function (Blueprint $table) {
            if (! Schema::hasColumn('material_products', 'barcode')) {
                $table->string('barcode')->nullable()->after('company_id');
                $table->index('barcode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_products', function (Blueprint $table) {
            if (Schema::hasColumn('material_products', 'barcode')) {
                $table->dropIndex(['barcode']);
                $table->dropColumn('barcode');
            }
        });
    }
};
