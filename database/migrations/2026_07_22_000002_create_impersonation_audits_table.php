<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('impersonation_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impersonator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('impersonated_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('impersonated_role')->nullable();
            $table->string('guard')->default('sanctum');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['impersonator_id', 'created_at']);
            $table->index(['impersonated_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_audits');
    }
};
