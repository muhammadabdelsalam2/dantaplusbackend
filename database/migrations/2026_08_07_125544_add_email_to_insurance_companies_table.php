<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            // اسم الطبيب مباشرة، للحالات اللي مفيش فيها User خالص
            $table->string('name')->nullable()->after('user_id');

            // علشان نقدر نعمل Doctor من غير ما نعمل User
            $table->foreignId('user_id')->nullable()->change();

            // (اختياري) نربط الطبيب مباشرة بالعيادة الخارجية لو مفيش user
            $table->foreignId('clinic_id')->nullable()->after('name')
                ->constrained('clinics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropColumn(['name', 'clinic_id']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
