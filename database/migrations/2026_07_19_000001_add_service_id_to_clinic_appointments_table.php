<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_appointments', 'service_id')) {
                $table->foreignId('service_id')
                    ->nullable()
                    ->after('patient_phone')
                    ->constrained('services')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_appointments', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
        });
    }
};
