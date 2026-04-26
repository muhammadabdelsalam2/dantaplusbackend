<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('insurance_price_lists')) {
            Schema::create('insurance_price_lists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->string('name');
                $table->unsignedSmallInteger('year');
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['clinic_id', 'year', 'name'], 'insurance_price_lists_clinic_year_name_unique');
                $table->index(['clinic_id', 'year']);
                $table->index(['clinic_id', 'name']);
            });
        }

        if (! Schema::hasTable('insurance_price_list_items')) {
            Schema::create('insurance_price_list_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('insurance_price_list_id')->constrained('insurance_price_lists')->cascadeOnDelete();
                $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
                $table->string('code')->nullable();
                $table->string('item_code')->nullable();
                $table->string('service_name');
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->string('category_name')->nullable();
                $table->decimal('price', 12, 2);
                $table->text('notes')->nullable();
                $table->timestamps();

              $table->index(
    ['insurance_price_list_id', 'service_id'],
    'ipl_items_list_service_idx'
);

$table->index(
    ['insurance_price_list_id', 'item_code'],
    'ipl_items_list_code_idx'
);
            });
        }

        if (! Schema::hasTable('insurance_price_list_import_logs')) {
            Schema::create('insurance_price_list_import_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('insurance_price_list_id')->nullable()->constrained('insurance_price_lists')->nullOnDelete();
                $table->string('import_key')->nullable();
                $table->string('source_file')->nullable();
                $table->json('payload')->nullable();
                $table->unsignedInteger('imported_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->string('status', 30)->default('completed');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['clinic_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_price_list_import_logs');
        Schema::dropIfExists('insurance_price_list_items');
        Schema::dropIfExists('insurance_price_lists');
    }
};
