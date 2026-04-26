<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('insurance_price_list_items')) {
            return;
        }

        Schema::table('insurance_price_list_items', function (Blueprint $table) {
            if (! Schema::hasColumn('insurance_price_list_items', 'code')) {
                $table->string('code')->nullable()->after('service_id');
            }

            if (! Schema::hasColumn('insurance_price_list_items', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('service_name')->constrained('categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('insurance_price_list_items', 'category_name')) {
                $table->string('category_name')->nullable()->after('category_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('insurance_price_list_items')) {
            return;
        }

        Schema::table('insurance_price_list_items', function (Blueprint $table) {
            if (Schema::hasColumn('insurance_price_list_items', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }

            foreach (['category_name', 'code'] as $column) {
                if (Schema::hasColumn('insurance_price_list_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
