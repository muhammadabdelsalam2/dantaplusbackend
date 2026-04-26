<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (! Schema::hasColumn('branches', 'phone')) {
                $table->string('phone', 50)->nullable()->after('city');
            }
            if (! Schema::hasColumn('branches', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('branches', 'working_hours_from')) {
                $table->string('working_hours_from', 20)->nullable()->after('manager_id');
            }
            if (! Schema::hasColumn('branches', 'working_hours_to')) {
                $table->string('working_hours_to', 20)->nullable()->after('working_hours_from');
            }
            if (! Schema::hasColumn('branches', 'notes')) {
                $table->text('notes')->nullable()->after('working_hours_to');
            }
            if (! Schema::hasColumn('branches', 'rooms_count')) {
                $table->unsignedTinyInteger('rooms_count')->default(1)->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            foreach (['rooms_count', 'notes', 'working_hours_to', 'working_hours_from', 'email', 'phone', 'city'] as $column) {
                if (Schema::hasColumn('branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
