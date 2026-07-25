<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lab_support_ticket_messages', function (Blueprint $table) {
            if (Schema::hasColumn('lab_support_ticket_messages', 'message')) {
                $table->text('message')->nullable()->change();
            }

            if (! Schema::hasColumn('lab_support_ticket_messages', 'attachment_url')) {
                $table->string('attachment_url')->nullable()->after('message');
            }

            if (! Schema::hasColumn('lab_support_ticket_messages', 'attachment_name')) {
                $table->string('attachment_name')->nullable()->after('attachment_url');
            }

            if (! Schema::hasColumn('lab_support_ticket_messages', 'attachment_mime')) {
                $table->string('attachment_mime', 120)->nullable()->after('attachment_name');
            }

            if (! Schema::hasColumn('lab_support_ticket_messages', 'attachment_size')) {
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_support_ticket_messages', function (Blueprint $table) {
            foreach (['attachment_size', 'attachment_mime', 'attachment_name', 'attachment_url'] as $column) {
                if (Schema::hasColumn('lab_support_ticket_messages', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('lab_support_ticket_messages', 'message')) {
                $table->text('message')->nullable(false)->change();
            }
        });
    }
};
