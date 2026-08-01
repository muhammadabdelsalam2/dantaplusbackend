<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clinic_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_invoices', 'reminder_sent')) {
                $table->boolean('reminder_sent')->default(false)->after('notes');
            }

            if (! Schema::hasColumn('clinic_invoices', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('reminder_sent');
            }

            if (! Schema::hasColumn('clinic_invoices', 'reminder_sent_by')) {
                $table->foreignId('reminder_sent_by')->nullable()->after('reminder_sent_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_invoices', 'reminder_sent_by')) {
                $table->dropConstrainedForeignId('reminder_sent_by');
            }

            foreach (['reminder_sent_at', 'reminder_sent'] as $column) {
                if (Schema::hasColumn('clinic_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
