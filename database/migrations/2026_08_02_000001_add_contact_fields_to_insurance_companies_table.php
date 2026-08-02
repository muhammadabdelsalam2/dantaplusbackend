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
            if (! Schema::hasColumn('insurance_companies', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('code');
            }

            if (! Schema::hasColumn('insurance_companies', 'phone')) {
                $table->string('phone', 50)->nullable()->after('contact_person');
            }

            if (! Schema::hasColumn('insurance_companies', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('insurance_companies')) {
            return;
        }

        Schema::table('insurance_companies', function (Blueprint $table) {
            foreach (['email', 'phone', 'contact_person'] as $column) {
                if (Schema::hasColumn('insurance_companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
