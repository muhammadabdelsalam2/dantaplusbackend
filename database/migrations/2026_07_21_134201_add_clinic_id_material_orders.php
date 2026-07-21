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
        Schema::table('material_orders', function (Blueprint $table) {
    $table->unsignedBigInteger('clinic_id')->nullable()->change();
    $table->decimal('shipping_cost', 10, 2)->nullable()->default(0)->change(); 
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
