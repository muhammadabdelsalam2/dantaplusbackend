<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('communication_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_conversations', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('clinic_id')->constrained('material_companies')->nullOnDelete();
            }
        });

        Schema::table('communication_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_messages', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('conversation_id')->constrained('material_companies')->nullOnDelete();
            }
            if (! Schema::hasColumn('communication_messages', 'message_type')) {
                $table->string('message_type', 30)->nullable()->after('type');
            }
            if (! Schema::hasColumn('communication_messages', 'content')) {
                $table->longText('content')->nullable()->after('text');
            }
            if (! Schema::hasColumn('communication_messages', 'related_type')) {
                $table->string('related_type')->nullable()->after('related_id');
            }
            if (! Schema::hasColumn('communication_messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('attachment_url');
            }
        });

        DB::table('communication_messages')->update([
            'message_type' => DB::raw("COALESCE(message_type, type)"),
            'content' => DB::raw("COALESCE(content, text)"),
            'attachment_path' => DB::raw("COALESCE(attachment_path, attachment_url)"),
        ]);

        DB::statement('
            UPDATE communication_conversations cc
            INNER JOIN communication_messages cm ON cm.conversation_id = cc.id
            SET cc.company_id = COALESCE(cc.company_id, cm.company_id)
        ');

        DB::statement('
            UPDATE communication_messages cm
            INNER JOIN communication_conversations cc ON cc.id = cm.conversation_id
            SET cm.company_id = COALESCE(cm.company_id, cc.company_id)
        ');
    }

    public function down(): void
    {
        Schema::table('communication_messages', function (Blueprint $table) {
            foreach (['company_id'] as $column) {
                if (Schema::hasColumn('communication_messages', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['message_type', 'content', 'related_type', 'attachment_path'] as $column) {
                if (Schema::hasColumn('communication_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('communication_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('communication_conversations', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
