<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'clinic_id')) {
                $table->foreignId('clinic_id')->nullable()->after('user_id')->constrained('clinics')->nullOnDelete();
            }

            if (!Schema::hasColumn('patients', 'address')) {
                $table->string('address')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('patients', 'medical_history')) {
                $table->text('medical_history')->nullable()->after('address');
            }

            if (!Schema::hasColumn('patients', 'allergies')) {
                $table->text('allergies')->nullable()->after('medical_history');
            }

            if (!Schema::hasColumn('patients', 'current_medication')) {
                $table->text('current_medication')->nullable()->after('allergies');
            }

            if (!Schema::hasColumn('patients', 'insurance_provider')) {
                $table->string('insurance_provider')->nullable()->after('current_medication');
            }

            if (!Schema::hasColumn('patients', 'insurance_number')) {
                $table->string('insurance_number')->nullable()->after('insurance_provider');
            }

            if (!Schema::hasColumn('patients', 'notes')) {
                $table->text('notes')->nullable()->after('insurance_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            foreach ([
                'clinic_id',
                'address',
                'medical_history',
                'allergies',
                'current_medication',
                'insurance_provider',
                'insurance_number',
                'notes',
            ] as $column) {
                if ($column === 'clinic_id' && Schema::hasColumn('patients', 'clinic_id')) {
                    $table->dropConstrainedForeignId('clinic_id');
                } elseif (Schema::hasColumn('patients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
