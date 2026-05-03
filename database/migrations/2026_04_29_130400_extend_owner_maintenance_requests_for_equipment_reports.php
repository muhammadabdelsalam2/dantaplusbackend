<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_maintenance_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_maintenance_requests', 'equipment_id')) {
                $table->foreignId('equipment_id')->nullable()->after('clinic_id')->constrained('equipments')->nullOnDelete();
            }

            if (! Schema::hasColumn('owner_maintenance_requests', 'malfunction_type')) {
                $table->string('malfunction_type')->nullable()->after('equipment');
            }

            if (! Schema::hasColumn('owner_maintenance_requests', 'urgency')) {
                $table->string('urgency', 30)->nullable()->after('issue_description');
            }

            if (! Schema::hasColumn('owner_maintenance_requests', 'attachment_url')) {
                $table->string('attachment_url')->nullable()->after('urgency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('owner_maintenance_requests', function (Blueprint $table) {
            if (Schema::hasColumn('owner_maintenance_requests', 'equipment_id')) {
                $table->dropConstrainedForeignId('equipment_id');
            }

            foreach (['malfunction_type', 'urgency', 'attachment_url'] as $column) {
                if (Schema::hasColumn('owner_maintenance_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
