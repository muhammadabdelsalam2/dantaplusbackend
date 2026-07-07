<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_note_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_note_id')->constrained('patient_notes')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('patient_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_note_attachments');
    }
};

