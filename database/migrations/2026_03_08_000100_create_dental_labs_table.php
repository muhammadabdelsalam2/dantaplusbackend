<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dental_labs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('address', 1000)->nullable();
            $table->string('city')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('working_hours')->nullable();
            $table->decimal('avg_delivery_days', 6, 2)->nullable();
            $table->enum('response_speed', ['Fast', 'Medium', 'Slow'])->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->string('logo_url')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->boolean('is_external')->default(false);
            $table->date('date_added')->nullable();
            $table->decimal('on_time_percentage', 5, 2)->nullable();
            $table->decimal('rejection_rate', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'city']);
            $table->index('status');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_labs');
    }
};
