<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('clinic_service_prices')) {
            return;
        }

        Schema::table('clinic_service_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_service_prices', 'cost')) {
                $table->decimal('cost', 10, 2)->default(0)->after('price');
            }

            if (! Schema::hasColumn('clinic_service_prices', 'lab_cost')) {
                $table->decimal('lab_cost', 10, 2)->default(0)->after('cost');
            }

            if (! Schema::hasColumn('clinic_service_prices', 'has_lab')) {
                $table->boolean('has_lab')->default(false)->after('lab_cost');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clinic_service_prices')) {
            return;
        }

        Schema::table('clinic_service_prices', function (Blueprint $table) {
            foreach (['has_lab', 'lab_cost', 'cost'] as $column) {
                if (Schema::hasColumn('clinic_service_prices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
