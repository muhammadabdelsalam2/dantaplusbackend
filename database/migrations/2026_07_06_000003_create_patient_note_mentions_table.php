<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_note_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_note_id')->constrained('patient_notes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['patient_note_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_note_mentions');
    }
};

