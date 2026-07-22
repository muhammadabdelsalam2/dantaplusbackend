<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cases MODIFY status ENUM('Pending','Accepted','In Progress','Completed','Delivered','Rejected','Received By Lab') NOT NULL DEFAULT 'Pending'");
        }

        Schema::table('cases', function (Blueprint $table) {
            if (! Schema::hasColumn('cases', 'lab_order_token')) {
                $table->uuid('lab_order_token')->nullable()->unique()->after('tooth_chart_3d');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (Schema::hasColumn('cases', 'lab_order_token')) {
                $table->dropUnique(['lab_order_token']);
                $table->dropColumn('lab_order_token');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cases MODIFY status ENUM('Pending','Accepted','In Progress','Completed','Delivered') NOT NULL DEFAULT 'Pending'");
        }
    }
};
