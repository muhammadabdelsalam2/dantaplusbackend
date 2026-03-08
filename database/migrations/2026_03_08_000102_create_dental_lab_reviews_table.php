<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dental_lab_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->date('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['lab_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_lab_reviews');
    }
};
