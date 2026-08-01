<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('patient_radiology', 'teeth')) {
            return;
        }

        Schema::table('patient_radiology', function (Blueprint $table) {
            $table->string('teeth')->nullable()->after('modality');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('patient_radiology', 'teeth')) {
            return;
        }

        Schema::table('patient_radiology', function (Blueprint $table) {
            $table->dropColumn('teeth');
        });
    }
};
