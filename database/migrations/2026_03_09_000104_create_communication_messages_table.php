<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('communication_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name')->nullable();
            $table->string('sender_type');
            $table->text('text')->nullable();
            $table->enum('type', ['text', 'attachment', 'system'])->default('text');
            $table->string('related_id')->nullable();
            $table->string('attachment_url')->nullable();
            $table->boolean('is_system_message')->default(false);
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_type', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
    }
};
