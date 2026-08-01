<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('syndicate_prices')) {
            return;
        }

        Schema::create('syndicate_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('code')->nullable();
            $table->string('service_name');
            $table->string('category')->nullable();
            $table->decimal('price', 12, 2);
            $table->boolean('is_active_year')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'year']);
            $table->unique(['clinic_id', 'year', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syndicate_prices');
    }
};
