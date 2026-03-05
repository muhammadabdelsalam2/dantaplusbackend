<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('material_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->decimal('commission_percentage', 5, 2);
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('country');
            $table->string('city')->nullable();
            $table->string('address', 1000)->nullable();
            $table->json('categories')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->boolean('is_featured')->default(false);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('last_commission_update')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'email', 'country']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_companies');
    }
};
