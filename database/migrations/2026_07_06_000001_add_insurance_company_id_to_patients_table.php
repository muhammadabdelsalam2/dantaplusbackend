<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'insurance_company_id')) {
                $table->foreignId('insurance_company_id')
                    ->nullable()
                    ->after('insurance_provider')
                    ->constrained('insurance_companies')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('patients', 'insurance_provider')) {
            DB::table('patients')
                ->whereNull('insurance_company_id')
                ->whereNotNull('insurance_provider')
                ->orderBy('id')
                ->each(function ($patient) {
                    $companyId = DB::table('insurance_companies')
                        ->where('clinic_id', $patient->clinic_id)
                        ->where('name', $patient->insurance_provider)
                        ->value('id');

                    if ($companyId) {
                        DB::table('patients')
                            ->where('id', $patient->id)
                            ->update(['insurance_company_id' => $companyId]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'insurance_company_id')) {
                $table->dropConstrainedForeignId('insurance_company_id');
            }
        });
    }
};

