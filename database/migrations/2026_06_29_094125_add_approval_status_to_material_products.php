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
    Schema::table('material_products', function (Blueprint $table) {
        $table->enum('approval_status', ['pending', 'approved', 'rejected'])
              ->default('pending')
              ->after('status');
        $table->text('rejection_reason')->nullable()->after('approval_status');
        $table->timestamp('approved_at')->nullable()->after('rejection_reason');
        $table->foreignId('approved_by')->nullable()->constrained('users')->after('approved_at');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('material_products', function (Blueprint $table) {
        $table->dropColumn(['approval_status', 'rejection_reason', 'approved_at', 'approved_by']);
    });
}
};
