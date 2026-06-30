<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            $table->string('receipt_proof_path')->nullable();
            $table->string('receipt_proof_original_name')->nullable();
            $table->string('receipt_proof_mime_type')->nullable();
            $table->integer('receipt_proof_size')->nullable();
            $table->decimal('trip_expense', 10, 2)->nullable();
            $table->timestamp('receipt_confirmed_at')->nullable();
            $table->foreignId('receipt_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            $table->dropForeign(['receipt_confirmed_by']);
            $table->dropColumn([
                'receipt_proof_path',
                'receipt_proof_original_name',
                'receipt_proof_mime_type',
                'receipt_proof_size',
                'trip_expense',
                'receipt_confirmed_at',
                'receipt_confirmed_by',
            ]);
        });
    }
};
