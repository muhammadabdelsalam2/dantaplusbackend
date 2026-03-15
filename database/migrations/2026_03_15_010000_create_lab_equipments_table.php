<?php

use App\Models\LabEquipment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained('dental_labs')->cascadeOnDelete();
            $table->string('name');
            $table->string('model_serial_number')->nullable();
            $table->date('purchase_date');
            $table->date('last_maintenance_date');
            $table->unsignedInteger('maintenance_cycle_days')->default(90);
            $table->enum('status', LabEquipment::STATUSES)->default(LabEquipment::STATUS_OPERATIONAL);
            $table->text('maintenance_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lab_id', 'status']);
            $table->index(['lab_id', 'last_maintenance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_equipments');
    }
};
