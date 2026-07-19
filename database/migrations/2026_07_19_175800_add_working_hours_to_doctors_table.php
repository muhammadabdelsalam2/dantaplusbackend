<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (! Schema::hasColumn('doctors', 'working_hours_from')) {
                $table->string('working_hours_from', 20)->nullable()->after('license_number');
            }

            if (! Schema::hasColumn('doctors', 'working_hours_to')) {
                $table->string('working_hours_to', 20)->nullable()->after('working_hours_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            foreach (['working_hours_to', 'working_hours_from'] as $column) {
                if (Schema::hasColumn('doctors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
