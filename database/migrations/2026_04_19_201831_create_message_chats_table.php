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
        Schema::create('message_chats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

            $table->text('message')->nullable();
            $table->enum('type', ['text', 'file', 'image', 'system'])->default('text');

            // reply system
            $table->foreignId('reply_to_id')->nullable()->constrained('message_chats')->nullOnDelete();

            // status
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();

            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();

            // flexible data
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_chats');
    }
};
