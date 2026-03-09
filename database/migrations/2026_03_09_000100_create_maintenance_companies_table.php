<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->string('logo_url')->nullable();
            $table->decimal('ai_rating', 3, 2)->nullable();
            $table->json('feedback')->nullable();
            $table->json('reports')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'status']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_companies');
    }
};
