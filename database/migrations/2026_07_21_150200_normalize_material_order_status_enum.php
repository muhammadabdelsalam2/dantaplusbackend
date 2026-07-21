<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_orders') || ! Schema::hasColumn('material_orders', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE material_orders
                MODIFY status VARCHAR(60) NOT NULL DEFAULT 'Pending Supplier Confirmation'
            ");
        }

        DB::table('material_orders')
            ->whereIn('status', ['pending', 'Pending', 'awaiting_clinic_confirmation'])
            ->update(['status' => OrderStatus::PENDING_SUPPLIER_CONFIRMATION]);

        DB::table('material_orders')
            ->whereIn('status', ['confirmed', 'Confirmed'])
            ->update(['status' => OrderStatus::ACCEPTED]);

        DB::table('material_orders')
            ->where('status', 'processing')
            ->update(['status' => OrderStatus::PROCESSING]);

        DB::table('material_orders')
            ->where('status', 'shipped')
            ->update(['status' => OrderStatus::SHIPPED]);

        DB::table('material_orders')
            ->where('status', 'delivered')
            ->update(['status' => OrderStatus::DELIVERED]);

        DB::table('material_orders')
            ->where('status', 'completed')
            ->update(['status' => OrderStatus::COMPLETED]);

        DB::table('material_orders')
            ->where('status', 'cancelled')
            ->update(['status' => OrderStatus::CANCELLED]);

        DB::table('material_orders')
            ->where('status', 'rejected')
            ->update(['status' => OrderStatus::REJECTED]);

        DB::table('material_orders')
            ->whereNotIn('status', OrderStatus::ALL)
            ->update(['status' => OrderStatus::PENDING_SUPPLIER_CONFIRMATION]);

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
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql' && Schema::hasTable('material_orders')) {
            DB::statement("
                ALTER TABLE material_orders
                MODIFY status VARCHAR(60) NOT NULL DEFAULT 'Pending Supplier Confirmation'
            ");
        }
    }
};
