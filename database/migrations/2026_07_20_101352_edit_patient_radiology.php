<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_radiology', function (Blueprint $table) {
            $table->string('before_image_path')->nullable()->after('file_path');
            $table->string('after_image_path')->nullable()->after('before_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('patient_radiology', function (Blueprint $table) {
            $table->dropColumn(['before_image_path', 'after_image_path']);
        });
    }
};
