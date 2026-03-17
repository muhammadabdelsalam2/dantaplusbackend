<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_whatsapp_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('action', 50);
            $table->text('details')->nullable();
            $table->string('status', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index('lab_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_whatsapp_api_logs');
    }
};
