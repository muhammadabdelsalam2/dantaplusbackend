<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dental_labs', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_clinic_id')->nullable()->after('id');
            $table->index('created_by_clinic_id');
        });
    }

    public function down(): void
    {
        Schema::table('dental_labs', function (Blueprint $table) {
            $table->dropColumn('created_by_clinic_id');
        });
    }
};
