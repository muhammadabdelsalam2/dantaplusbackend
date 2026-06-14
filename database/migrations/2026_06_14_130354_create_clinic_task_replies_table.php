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
    Schema::create('clinic_task_replies', function (Blueprint $table) {
        $table->id();
        $table->foreignId('clinic_task_id')->constrained()->cascadeOnDelete();
        $table->foreignId('created_by')->constrained('users');
        $table->text('message');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinic_task_replies');
    }
};
