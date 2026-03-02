<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner_name');
            $table->string('email')->unique();
            $table->string('phone', 50);
            $table->string('address', 1000);
            $table->enum('subscription_plan', ['Basic', 'Standard', 'Premium']);
            $table->enum('payment_method', ['Stripe', 'PayPal', 'Manual']);
            $table->enum('status', ['Active', 'Trial', 'Expired', 'Suspended'])->default('Trial');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->unsignedInteger('max_users')->default(0);
            $table->unsignedInteger('max_branches')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'owner_name', 'email']);
            $table->index(['status', 'subscription_plan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
