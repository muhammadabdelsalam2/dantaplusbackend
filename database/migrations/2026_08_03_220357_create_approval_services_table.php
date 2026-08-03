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
        Schema::create('approval_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_approval_id')->constrained('insurance_approvals')->cascadeOnDelete();
            $table->string('service_name');
            $table->string('service_code')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('co_pay', 12, 2)->default(0);
            $table->string('tooth_number', 20)->nullable();
            $table->timestamps();

            $table->index(['insurance_approval_id', 'service_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_services');
    }
};
