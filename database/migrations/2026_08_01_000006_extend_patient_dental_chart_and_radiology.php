<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('patient_teeth', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_teeth', 'is_present')) {
                $table->boolean('is_present')->default(true)->after('status');
            }

            if (! Schema::hasColumn('patient_teeth', 'target_area')) {
                $table->string('target_area')->nullable()->after('is_present');
            }

            if (! Schema::hasColumn('patient_teeth', 'procedure_id')) {
                $table->foreignId('procedure_id')->nullable()->after('target_area')->constrained('services')->nullOnDelete();
            }

            if (! Schema::hasColumn('patient_teeth', 'treating_doctor_id')) {
                $table->foreignId('treating_doctor_id')->nullable()->after('procedure_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('patient_teeth', 'billing_method')) {
                $table->string('billing_method', 50)->nullable()->after('treating_doctor_id');
            }

            if (! Schema::hasColumn('patient_teeth', 'clinical_notes')) {
                $table->text('clinical_notes')->nullable()->after('billing_method');
            }
        });

        Schema::table('patient_radiology', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_radiology', 'record_date')) {
                $table->date('record_date')->nullable()->after('teeth');
            }

            if (! Schema::hasColumn('patient_radiology', 'linked_appointment_id')) {
                $table->foreignId('linked_appointment_id')->nullable()->after('record_date')->constrained('clinic_appointments')->nullOnDelete();
            }

            if (! Schema::hasColumn('patient_radiology', 'linked_treatment_id')) {
                $table->foreignId('linked_treatment_id')->nullable()->after('linked_appointment_id')->constrained('clinic_treatments')->nullOnDelete();
            }

            if (! Schema::hasColumn('patient_radiology', 'report_reference_code')) {
                $table->string('report_reference_code')->nullable()->unique()->after('status');
            }

            if (! Schema::hasColumn('patient_radiology', 'report_format')) {
                $table->string('report_format')->nullable()->after('report_reference_code');
            }

            if (! Schema::hasColumn('patient_radiology', 'report_case_selection')) {
                $table->json('report_case_selection')->nullable()->after('report_format');
            }

            if (! Schema::hasColumn('patient_radiology', 'report_findings')) {
                $table->text('report_findings')->nullable()->after('report_case_selection');
            }

            if (! Schema::hasColumn('patient_radiology', 'report_diagnosis')) {
                $table->text('report_diagnosis')->nullable()->after('report_findings');
            }

            if (! Schema::hasColumn('patient_radiology', 'report_generated_by')) {
                $table->foreignId('report_generated_by')->nullable()->after('report_diagnosis')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('patient_radiology', 'report_generated_at')) {
                $table->timestamp('report_generated_at')->nullable()->after('report_generated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patient_radiology', function (Blueprint $table) {
            if (Schema::hasColumn('patient_radiology', 'report_reference_code')) {
                $table->dropUnique(['report_reference_code']);
            }

            foreach (['report_generated_at', 'report_diagnosis', 'report_findings', 'report_case_selection', 'report_format', 'report_reference_code', 'linked_treatment_id', 'linked_appointment_id', 'record_date'] as $column) {
                if (Schema::hasColumn('patient_radiology', $column)) {
                    if (in_array($column, ['linked_treatment_id', 'linked_appointment_id'], true)) {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }

            if (Schema::hasColumn('patient_radiology', 'report_generated_by')) {
                $table->dropConstrainedForeignId('report_generated_by');
            }
        });

        Schema::table('patient_teeth', function (Blueprint $table) {
            foreach (['clinical_notes', 'billing_method', 'target_area', 'is_present'] as $column) {
                if (Schema::hasColumn('patient_teeth', $column)) {
                    $table->dropColumn($column);
                }
            }

            foreach (['treating_doctor_id', 'procedure_id'] as $column) {
                if (Schema::hasColumn('patient_teeth', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
