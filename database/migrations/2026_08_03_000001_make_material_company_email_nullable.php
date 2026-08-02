<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('material_companies') || ! Schema::hasColumn('material_companies', 'email')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE material_companies MODIFY email VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_companies') || ! Schema::hasColumn('material_companies', 'email')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::table('material_companies')
                ->whereNull('email')
                ->update(['email' => DB::raw("CONCAT('supplier-', id, '@placeholder.local')")]);

            DB::statement('ALTER TABLE material_companies MODIFY email VARCHAR(255) NOT NULL');
        }
    }
};
