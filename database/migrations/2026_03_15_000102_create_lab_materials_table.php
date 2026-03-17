<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')
                ->constrained('dental_labs')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('supplier');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->date('purchase_date');
            $table->date('expiration_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lab_id', 'name']);
            $table->index(['lab_id', 'supplier']);
            $table->index(['lab_id', 'expiration_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_materials');
    }
};
