<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('insurance_companies')) {
            if (! Schema::hasColumn('insurance_companies', 'syndicate_price_list_id')) {
                Schema::table('insurance_companies', function (Blueprint $table) {
                    $table->foreignId('syndicate_price_list_id')
                        ->nullable()
                        ->after('clinic_id')
                        ->constrained('insurance_price_lists')
                        ->nullOnDelete();
                });
            }

            return;
        }

        Schema::create('insurance_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('syndicate_price_list_id')->nullable()->constrained('insurance_price_lists')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('coverage')->nullable();
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'name']);
            $table->index(['clinic_id', 'syndicate_price_list_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('insurance_companies')) {
            return;
        }

        if (Schema::hasColumn('insurance_companies', 'syndicate_price_list_id')) {
            Schema::table('insurance_companies', function (Blueprint $table) {
                $table->dropConstrainedForeignId('syndicate_price_list_id');
            });
        }
    }
};
