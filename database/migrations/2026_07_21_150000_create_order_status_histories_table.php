<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('material_orders')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
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
                    'awaiting_clinic_confirmation',
                    'Pending',
                    'Confirmed',
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

        DB::table('material_orders')
            ->whereIn('status', ['pending', 'Pending'])
            ->update(['status' => OrderStatus::PENDING_SUPPLIER_CONFIRMATION]);

        DB::table('material_orders')
            ->whereIn('status', ['confirmed', 'Confirmed'])
            ->update(['status' => OrderStatus::ACCEPTED]);

        DB::table('material_orders')
            ->whereIn('status', ['processing'])
            ->update(['status' => OrderStatus::PROCESSING]);

        DB::table('material_orders')
            ->whereIn('status', ['shipped'])
            ->update(['status' => OrderStatus::SHIPPED]);

        DB::table('material_orders')
            ->whereIn('status', ['delivered'])
            ->update(['status' => OrderStatus::DELIVERED]);

        DB::table('material_orders')
            ->whereIn('status', ['completed'])
            ->update(['status' => OrderStatus::COMPLETED]);

        DB::table('material_orders')
            ->whereIn('status', ['cancelled'])
            ->update(['status' => OrderStatus::CANCELLED]);

        DB::table('material_orders')
            ->whereIn('status', ['rejected', 'awaiting_clinic_confirmation'])
            ->update(['status' => OrderStatus::REJECTED]);

        if ($driver === 'mysql') {
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
        Schema::dropIfExists('order_status_histories');
    }
};
