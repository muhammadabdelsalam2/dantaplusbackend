<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()
                ->after('room')
                ->constrained('rooms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {   
            $table->dropConstrainedForeignId('room_id');
        });
    }
};
