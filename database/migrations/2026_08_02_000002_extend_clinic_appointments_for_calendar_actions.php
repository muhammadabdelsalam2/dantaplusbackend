<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('clinic_appointments')) {
            return;
        }

        Schema::table('clinic_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_appointments', 'rescheduled_from_id')) {
                $table->foreignId('rescheduled_from_id')->nullable()->after('payment_type')->constrained('clinic_appointments')->nullOnDelete();
            }

            if (! Schema::hasColumn('clinic_appointments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('rescheduled_from_id');
            }

            if (! Schema::hasColumn('clinic_appointments', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clinic_appointments')) {
            return;
        }

        Schema::table('clinic_appointments', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_appointments', 'rescheduled_from_id')) {
                $table->dropConstrainedForeignId('rescheduled_from_id');
            }

            foreach (['completed_at', 'cancelled_at'] as $column) {
                if (Schema::hasColumn('clinic_appointments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
