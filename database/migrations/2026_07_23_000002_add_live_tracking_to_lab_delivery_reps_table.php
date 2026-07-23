<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lab_delivery_reps', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_delivery_reps', 'last_latitude')) {
                $table->decimal('last_latitude', 10, 7)->nullable()->after('status');
            }

            if (! Schema::hasColumn('lab_delivery_reps', 'last_longitude')) {
                $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
            }

            if (! Schema::hasColumn('lab_delivery_reps', 'tracking_status')) {
                $table->string('tracking_status')->nullable()->after('last_longitude');
            }

            if (! Schema::hasColumn('lab_delivery_reps', 'last_location_at')) {
                $table->timestamp('last_location_at')->nullable()->after('tracking_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_delivery_reps', function (Blueprint $table) {
            foreach (['last_latitude', 'last_longitude', 'tracking_status', 'last_location_at'] as $column) {
                if (Schema::hasColumn('lab_delivery_reps', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
