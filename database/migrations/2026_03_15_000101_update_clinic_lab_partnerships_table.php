<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clinic_lab_partnerships', function (Blueprint $table) {
            if (!Schema::hasColumn('clinic_lab_partnerships', 'partnership_start_date')) {
                $table->date('partnership_start_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('clinic_lab_partnerships', 'last_case_date')) {
                $table->date('last_case_date')->nullable()->after('total_cases_sent');
            }
            if (!Schema::hasColumn('clinic_lab_partnerships', 'invited_by')) {
                $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete()->after('last_case_date');
            }

            $table->index(['lab_id', 'status']);
        });

        DB::table('clinic_lab_partnerships')->where('status', 'Inactive')->update(['status' => 'Ended']);

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE clinic_lab_partnerships MODIFY status ENUM('Active','Pending','Paused','Ended') NOT NULL DEFAULT 'Pending'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE clinic_lab_partnerships MODIFY status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'");
        }

        Schema::table('clinic_lab_partnerships', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_lab_partnerships', 'invited_by')) {
                $table->dropConstrainedForeignId('invited_by');
            }
            if (Schema::hasColumn('clinic_lab_partnerships', 'last_case_date')) {
                $table->dropColumn('last_case_date');
            }
            if (Schema::hasColumn('clinic_lab_partnerships', 'partnership_start_date')) {
                $table->dropColumn('partnership_start_date');
            }

            $table->dropIndex(['lab_id', 'status']);
        });
    }
};
