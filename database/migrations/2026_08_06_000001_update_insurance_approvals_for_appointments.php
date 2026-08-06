<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addAppointmentColumn();

        $this->setInsuranceCompanyNullable(true);
    }

    public function down(): void
    {
        $this->dropAppointmentColumn();

        $this->setInsuranceCompanyNullable(false);
    }

    private function addAppointmentColumn(): void
    {
        if (Schema::hasColumn('insurance_approvals', 'appointment_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE insurance_approvals ADD appointment_id BIGINT UNSIGNED NULL AFTER patient_id');
            DB::statement('ALTER TABLE insurance_approvals ADD CONSTRAINT insurance_approvals_appointment_id_foreign FOREIGN KEY (appointment_id) REFERENCES clinic_appointments(id) ON DELETE SET NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE insurance_approvals ADD COLUMN appointment_id BIGINT NULL');
            DB::statement('ALTER TABLE insurance_approvals ADD CONSTRAINT insurance_approvals_appointment_id_foreign FOREIGN KEY (appointment_id) REFERENCES clinic_appointments(id) ON DELETE SET NULL');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('ALTER TABLE insurance_approvals ADD COLUMN appointment_id INTEGER NULL');
        }
    }

    private function dropAppointmentColumn(): void
    {
        if (! Schema::hasColumn('insurance_approvals', 'appointment_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE insurance_approvals DROP FOREIGN KEY insurance_approvals_appointment_id_foreign');
            DB::statement('ALTER TABLE insurance_approvals DROP COLUMN appointment_id');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE insurance_approvals DROP CONSTRAINT IF EXISTS insurance_approvals_appointment_id_foreign');
            DB::statement('ALTER TABLE insurance_approvals DROP COLUMN appointment_id');
            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('insurance_approvals', fn ($table) => $table->dropColumn('appointment_id'));
        }
    }

    private function setInsuranceCompanyNullable(bool $nullable): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE insurance_approvals MODIFY insurance_company_id BIGINT UNSIGNED ' . ($nullable ? 'NULL' : 'NOT NULL')
            );
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE insurance_approvals ALTER COLUMN insurance_company_id ' . ($nullable ? 'DROP NOT NULL' : 'SET NOT NULL')
            );
        }
    }
};
