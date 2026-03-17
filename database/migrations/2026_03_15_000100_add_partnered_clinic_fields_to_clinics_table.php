<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            if (!Schema::hasColumn('clinics', 'subdomain')) {
                $table->string('subdomain')->nullable()->after('address');
            }
            if (!Schema::hasColumn('clinics', 'clinic_type')) {
                $table->string('clinic_type', 50)->nullable()->after('subdomain');
            }
            if (!Schema::hasColumn('clinics', 'is_external')) {
                $table->boolean('is_external')->default(false)->after('clinic_type');
            }
            if (!Schema::hasColumn('clinics', 'notes')) {
                $table->text('notes')->nullable()->after('is_external');
            }
            if (!Schema::hasColumn('clinics', 'added_by')) {
                $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete()->after('notes');
            }
            if (!Schema::hasColumn('clinics', 'registration_date')) {
                $table->date('registration_date')->nullable()->after('added_by');
            }

            $table->index('is_external');
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE clinics MODIFY email VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clinics MODIFY address TEXT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE clinics MODIFY email VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE clinics MODIFY address VARCHAR(1000) NOT NULL');
        }

        Schema::table('clinics', function (Blueprint $table) {
            if (Schema::hasColumn('clinics', 'registration_date')) {
                $table->dropColumn('registration_date');
            }
            if (Schema::hasColumn('clinics', 'added_by')) {
                $table->dropConstrainedForeignId('added_by');
            }
            if (Schema::hasColumn('clinics', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('clinics', 'is_external')) {
                $table->dropColumn('is_external');
            }
            if (Schema::hasColumn('clinics', 'clinic_type')) {
                $table->dropColumn('clinic_type');
            }
            if (Schema::hasColumn('clinics', 'subdomain')) {
                $table->dropColumn('subdomain');
            }
        });
    }
};
