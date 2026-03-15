<?php

use App\Models\LabDeliveryRep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_delivery_reps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->string('assigned_region_city')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('profile_photo')->nullable();
            $table->string('status')->default(LabDeliveryRep::STATUS_ACTIVE);
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['lab_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_delivery_reps');
    }
};
