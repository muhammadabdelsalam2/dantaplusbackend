<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->enum('severity', ['Low', 'Medium', 'High', 'Critical'])->default('Low');
            $table->foreignId('company_id')->nullable()->constrained('maintenance_companies')->nullOnDelete();
            $table->foreignId('maintenance_request_id')->nullable()->constrained('owner_maintenance_requests')->nullOnDelete();
            $table->boolean('is_reviewed')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['is_reviewed', 'severity']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_alerts');
    }
};
