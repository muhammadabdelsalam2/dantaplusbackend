<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lab_support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_support_tickets', 'ticket_number')) {
                $table->string('ticket_number', 20)->nullable()->unique()->after('id');
            }
        });

        Schema::create('lab_support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('lab_support_tickets')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name')->nullable();
            $table->string('sender_type', 50)->default('lab');
            $table->text('message');
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::table('lab_equipments', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_equipments', 'next_due_date')) {
                $table->date('next_due_date')->nullable()->after('last_maintenance_date');
            }

            if (! Schema::hasColumn('lab_equipments', 'maintenance_status')) {
                $table->string('maintenance_status', 50)->nullable()->after('next_due_date');
            }
        });

        Schema::create('equipment_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('lab_equipments')->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->date('maintenance_date');
            $table->date('next_due_date')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['equipment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_logs');

        Schema::table('lab_equipments', function (Blueprint $table) {
            foreach (['next_due_date', 'maintenance_status'] as $column) {
                if (Schema::hasColumn('lab_equipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('lab_support_ticket_messages');

        Schema::table('lab_support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('lab_support_tickets', 'ticket_number')) {
                $table->dropUnique(['ticket_number']);
                $table->dropColumn('ticket_number');
            }
        });
    }
};
