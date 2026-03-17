<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_gallery_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('url', 500);
            $table->string('disk', 20)->default('public');
            $table->unsignedSmallInteger('sort_order')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lab_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_gallery_images');
    }
};
