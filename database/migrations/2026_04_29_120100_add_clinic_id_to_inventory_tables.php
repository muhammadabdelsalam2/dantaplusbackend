<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'clinic_id')) {
                $table->foreignId('clinic_id')->nullable()->after('company_id')->constrained('clinics')->nullOnDelete();
                $table->index(['clinic_id', 'status']);
                $table->index(['clinic_id', 'quantity']);
            }
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_logs', 'clinic_id')) {
                $table->foreignId('clinic_id')->nullable()->after('company_id')->constrained('clinics')->nullOnDelete();
                $table->index(['clinic_id', 'action']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_logs', 'clinic_id')) {
                $table->dropIndex(['clinic_id', 'action']);
                $table->dropConstrainedForeignId('clinic_id');
            }
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'clinic_id')) {
                $table->dropIndex(['clinic_id', 'status']);
                $table->dropIndex(['clinic_id', 'quantity']);
                $table->dropConstrainedForeignId('clinic_id');
            }
        });
    }
};
