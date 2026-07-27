<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUS_PENDING_SUPPLIER_CONFIRMATION = 'Pending Supplier Confirmation';
    private const STATUS_ACCEPTED = 'Accepted';
    private const STATUS_PROCESSING = 'Processing';
    private const STATUS_SHIPPED = 'Shipped';
    private const STATUS_DELIVERED = 'Delivered';
    private const STATUS_COMPLETED = 'Completed';
    private const STATUS_CANCELLED = 'Cancelled';
    private const STATUS_REJECTED = 'Rejected';

    public function up(): void
    {
        if (! Schema::hasTable('material_orders')) {
            return;
        }

        Schema::table('material_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('material_orders', 'supplier_note')) {
                $table->text('supplier_note')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('material_orders', 'modified_by_supplier')) {
                $table->boolean('modified_by_supplier')->default(false)->after('supplier_note');
            }
        });

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('material_orders')
            ->whereIn('status', ['pending', 'Pending', 'awaiting_clinic_confirmation'])
            ->update(['status' => self::STATUS_PENDING_SUPPLIER_CONFIRMATION]);

        DB::table('material_orders')
            ->whereIn('status', ['confirmed', 'Confirmed'])
            ->update(['status' => self::STATUS_ACCEPTED]);

        DB::table('material_orders')
            ->where('status', 'processing')
            ->update(['status' => self::STATUS_PROCESSING]);

        DB::table('material_orders')
            ->where('status', 'shipped')
            ->update(['status' => self::STATUS_SHIPPED]);

        DB::table('material_orders')
            ->where('status', 'delivered')
            ->update(['status' => self::STATUS_DELIVERED]);

        DB::table('material_orders')
            ->where('status', 'completed')
            ->update(['status' => self::STATUS_COMPLETED]);

        DB::table('material_orders')
            ->where('status', 'cancelled')
            ->update(['status' => self::STATUS_CANCELLED]);

        DB::table('material_orders')
            ->where('status', 'rejected')
            ->update(['status' => self::STATUS_REJECTED]);

        DB::statement("
            ALTER TABLE material_orders
            MODIFY status ENUM(
                'Pending Supplier Confirmation',
                'Accepted',
                'Processing',
                'Shipped',
                'Delivered',
                'Completed',
                'Cancelled',
                'Rejected'
            ) NOT NULL DEFAULT 'Pending Supplier Confirmation'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_orders')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE material_orders
                MODIFY status ENUM(
                    'Pending Supplier Confirmation',
                    'Accepted',
                    'Processing',
                    'Shipped',
                    'Delivered',
                    'Completed',
                    'Cancelled',
                    'Rejected'
                ) NOT NULL DEFAULT 'Pending Supplier Confirmation'
            ");
        }

        Schema::table('material_orders', function (Blueprint $table) {
            foreach (['supplier_note', 'modified_by_supplier'] as $column) {
                if (Schema::hasColumn('material_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
