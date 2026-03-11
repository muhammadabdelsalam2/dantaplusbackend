<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name');
            $table->string('sender_role');
            $table->text('message');
            $table->timestamps();

            $table->index('support_ticket_id');
            $table->index('sender_id');
            $table->index('sender_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_replies');
    }
};
