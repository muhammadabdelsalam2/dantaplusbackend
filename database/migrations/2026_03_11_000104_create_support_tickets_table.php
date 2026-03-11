<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('reporter_type');
            $table->unsignedBigInteger('reporter_id');
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('lab_id')->nullable()->constrained('dental_labs')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->string('priority');
            $table->string('status');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();

            $table->index('reporter_type');
            $table->index('reporter_id');
            $table->index('clinic_id');
            $table->index('lab_id');
            $table->index('priority');
            $table->index('status');
            $table->index('assigned_to');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
