<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->string('audience_type')->nullable();
            $table->unsignedBigInteger('audience_id')->nullable();
            $table->string('priority')->nullable();
            $table->json('delivery_methods')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_test')->default(false);
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name')->nullable();
            $table->string('link')->nullable();
            $table->timestamps();

            $table->index(['type', 'priority']);
            $table->index('audience_type');
            $table->index('audience_id');
            $table->index('is_read');
            $table->index('status');
            $table->index('sender_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
