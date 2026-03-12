<?php

use App\Models\LabSupportTicket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_support_tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lab_id')
                ->constrained('dental_labs')
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->string('category', 100);
            $table->enum('priority', [
                LabSupportTicket::PRIORITY_LOW,
                LabSupportTicket::PRIORITY_MEDIUM,
                LabSupportTicket::PRIORITY_HIGH,
            ])->default(LabSupportTicket::PRIORITY_MEDIUM);

            $table->enum('status', [
                LabSupportTicket::STATUS_OPEN,
                LabSupportTicket::STATUS_IN_PROGRESS,
                LabSupportTicket::STATUS_RESOLVED,
            ])->default(LabSupportTicket::STATUS_OPEN);

            $table->text('description');
            $table->string('attachment_url')->nullable();

            $table->timestamps();

            $table->index(['lab_id', 'status']);
            $table->index(['lab_id', 'priority']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_support_tickets');
    }
};
