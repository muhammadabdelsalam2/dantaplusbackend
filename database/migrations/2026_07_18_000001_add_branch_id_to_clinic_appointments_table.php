<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_appointments', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('duration')->constrained('branches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_appointments', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });
    }
};
