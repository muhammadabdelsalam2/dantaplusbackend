<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lab_equipments', function (Blueprint $table) {
            if (Schema::hasColumn('lab_equipments', 'maintenance_status')) {
                $table->dropColumn('maintenance_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_equipments', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_equipments', 'maintenance_status')) {
                $table->string('maintenance_status')->nullable();
            }
        });
    }
};
