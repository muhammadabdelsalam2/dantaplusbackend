<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Prepare for multi-tenant (future): platform / clinic / lab
            $table->string('scope_type', 20)->default('platform');
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->string('group', 50);
            $table->string('key', 100);
            $table->json('value')->nullable();

            // Store encrypted values safely (e.g. API keys)
            $table->boolean('is_encrypted')->default(false);

            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'group', 'key'], 'settings_scope_group_key_unique');
            $table->index(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
