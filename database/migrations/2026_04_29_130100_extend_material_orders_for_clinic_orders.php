<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('material_orders', 'supplier_note')) {
                $table->text('supplier_note')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('material_orders', 'modified_by_supplier')) {
                $table->boolean('modified_by_supplier')->default(false)->after('supplier_note');
            }
        });

   DB::statement("
    ALTER TABLE material_orders
    MODIFY status ENUM(
        'pending',
        'confirmed',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
        'completed',
        'awaiting_clinic_confirmation'
    ) NOT NULL DEFAULT 'pending'
");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE material_orders
            MODIFY status ENUM(
                'Pending',
                'Confirmed',
                'Processing',
                'Shipped',
                'Delivered',
                'Cancelled'
            ) NOT NULL DEFAULT 'Pending'
        ");

        Schema::table('material_orders', function (Blueprint $table) {
            foreach (['supplier_note', 'modified_by_supplier'] as $column) {
                if (Schema::hasColumn('material_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
