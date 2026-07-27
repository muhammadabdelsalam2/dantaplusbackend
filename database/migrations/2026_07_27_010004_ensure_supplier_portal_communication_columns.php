<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('communication_conversations') && ! Schema::hasColumn('communication_conversations', 'company_id')) {
            Schema::table('communication_conversations', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('clinic_id')->constrained('material_companies')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('communication_messages')) {
            return;
        }

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
    }

    public function down(): void
    {
        if (Schema::hasTable('communication_messages')) {
            Schema::table('communication_messages', function (Blueprint $table) {
                if (Schema::hasColumn('communication_messages', 'company_id')) {
                    $table->dropConstrainedForeignId('company_id');
                }

                foreach (['message_type', 'content', 'related_type', 'attachment_path'] as $column) {
                    if (Schema::hasColumn('communication_messages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('communication_conversations') && Schema::hasColumn('communication_conversations', 'company_id')) {
            Schema::table('communication_conversations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }
};
