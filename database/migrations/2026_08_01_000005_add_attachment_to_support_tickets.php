<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('description');
            }

            if (! Schema::hasColumn('support_tickets', 'attachment_name')) {
                $table->string('attachment_name')->nullable()->after('attachment_path');
            }

            if (! Schema::hasColumn('support_tickets', 'attachment_mime')) {
                $table->string('attachment_mime')->nullable()->after('attachment_name');
            }

            if (! Schema::hasColumn('support_tickets', 'attachment_size')) {
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            foreach (['attachment_size', 'attachment_mime', 'attachment_name', 'attachment_path'] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
