<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('material_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('material_orders', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('supplier_company_id')->constrained('material_companies')->nullOnDelete();
            }
            if (! Schema::hasColumn('material_orders', 'external_clinic_name')) {
                $table->string('external_clinic_name')->nullable()->after('clinic_id');
            }
            if (! Schema::hasColumn('material_orders', 'external_clinic_phone')) {
                $table->string('external_clinic_phone', 50)->nullable()->after('external_clinic_name');
            }
            if (! Schema::hasColumn('material_orders', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0)->after('amount_total');
            }
            if (! Schema::hasColumn('material_orders', 'source')) {
                $table->string('source', 20)->default('online')->after('payment_status');
            }
            if (! Schema::hasColumn('material_orders', 'delivery_address')) {
                $table->string('delivery_address', 1000)->nullable()->after('source');
            }
            if (! Schema::hasColumn('material_orders', 'delivery_at')) {
                $table->timestamp('delivery_at')->nullable()->after('delivery_address');
            }
            if (! Schema::hasColumn('material_orders', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('delivery_at')->constrained('users')->nullOnDelete();
            }
        });

        DB::table('material_orders')->update([
            'company_id' => DB::raw('COALESCE(company_id, supplier_company_id)'),
            'total_amount' => DB::raw('COALESCE(total_amount, amount_total)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('material_orders', function (Blueprint $table) {
            foreach (['created_by', 'company_id'] as $column) {
                if (Schema::hasColumn('material_orders', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['external_clinic_name', 'external_clinic_phone', 'total_amount', 'source', 'delivery_address', 'delivery_at'] as $column) {
                if (Schema::hasColumn('material_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
