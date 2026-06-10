<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('insurance_claims')) {
            if (!Schema::hasColumn('insurance_claims', 'patient_consent_required')) {
                Schema::table('insurance_claims', function (Blueprint $table) {
                    $table->boolean('patient_consent_required')->default(false)->after('status');
                    $table->foreignId('patient_consent_document_id')->nullable()->after('patient_consent_required')->constrained('patient_documents')->nullOnDelete();
                    $table->timestamp('patient_consent_uploaded_at')->nullable()->after('patient_consent_document_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('insurance_claims')) {
            Schema::table('insurance_claims', function (Blueprint $table) {
                if (Schema::hasColumn('insurance_claims', 'patient_consent_required')) {
                    $table->dropColumn(['patient_consent_required', 'patient_consent_document_id', 'patient_consent_uploaded_at']);
                }
            });
        }
    }
};
