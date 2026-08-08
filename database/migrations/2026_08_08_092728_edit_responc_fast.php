<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // doctrine/dbal مطلوب عشان change() تشتغل على enum -> string
        // composer require doctrine/dbal

        Schema::table('dental_labs', function (Blueprint $table) {
            $table->string('response_speed', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dental_labs', function (Blueprint $table) {
            $table->enum('response_speed', ['Fast', 'Medium', 'Slow'])->nullable()->change();
        });
    }
};
