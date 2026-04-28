<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clinic_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_invoices', 'doctor_user_id')) {
                $table->foreignId('doctor_user_id')->nullable()->after('patient_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('clinic_invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('issued_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_invoices', 'doctor_user_id')) {
                $table->dropConstrainedForeignId('doctor_user_id');
            }

            if (Schema::hasColumn('clinic_invoices', 'due_date')) {
                $table->dropColumn('due_date');
            }
        });
    }
};
