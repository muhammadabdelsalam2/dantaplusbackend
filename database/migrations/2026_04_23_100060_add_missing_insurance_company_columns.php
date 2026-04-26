<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('insurance_companies')) {
            return;
        }

        Schema::table('insurance_companies', function (Blueprint $table) {
            if (! Schema::hasColumn('insurance_companies', 'coverage')) {
                $table->string('coverage')->nullable()->after('code');
            }

            if (! Schema::hasColumn('insurance_companies', 'payment_terms')) {
                $table->string('payment_terms')->nullable()->after('coverage');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('insurance_companies')) {
            return;
        }

        Schema::table('insurance_companies', function (Blueprint $table) {
            foreach (['payment_terms', 'coverage'] as $column) {
                if (Schema::hasColumn('insurance_companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
