<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('lab_id')->nullable()->constrained('dental_labs')->nullOnDelete();
            $table->enum('status', ['Open', 'Pending', 'Resolved', 'Closed'])->default('Open');
            $table->text('last_message_text')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->foreignId('last_message_sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
            $table->index(['clinic_id', 'lab_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_conversations');
    }
};
