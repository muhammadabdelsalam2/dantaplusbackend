<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_appointments', 'branch')) {
                $table->string('branch')->nullable()->after('duration_minutes');
            }

            if (! Schema::hasColumn('clinic_appointments', 'room')) {
                $table->string('room')->nullable()->after('branch');
            }

            if (! Schema::hasColumn('clinic_appointments', 'duration')) {
                $table->unsignedInteger('duration')->default(30)->after('duration_minutes');
            }
        });

        Schema::table('clinic_treatments', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_treatments', 'tooth_number')) {
                $table->string('tooth_number')->nullable()->after('description');
            }

            if (! Schema::hasColumn('clinic_treatments', 'sessions_count')) {
                $table->unsignedInteger('sessions_count')->default(1)->after('tooth_number');
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'patient_number')) {
                $table->string('patient_number')->nullable()->unique()->after('clinic_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            foreach (['duration', 'room', 'branch'] as $column) {
                if (Schema::hasColumn('clinic_appointments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('clinic_treatments', function (Blueprint $table) {
            foreach (['tooth_number', 'sessions_count'] as $column) {
                if (Schema::hasColumn('clinic_treatments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'patient_number')) {
                $table->dropUnique(['patient_number']);
                $table->dropColumn('patient_number');
            }
        });
    }
};
