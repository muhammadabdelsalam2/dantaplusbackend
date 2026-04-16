<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('material_products', function (Blueprint $table) {
            if (! Schema::hasColumn('material_products', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('company_id')->constrained('categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('material_products', 'image_path')) {
                $table->string('image_path')->nullable()->after('image_url');
            }
            if (! Schema::hasColumn('material_products', 'estimated_delivery_time')) {
                $table->string('estimated_delivery_time')->nullable()->after('status');
            }
            if (! Schema::hasColumn('material_products', 'rating')) {
                $table->decimal('rating', 3, 2)->default(0)->after('estimated_delivery_time');
            }
            if (! Schema::hasColumn('material_products', 'review_count')) {
                $table->unsignedInteger('review_count')->default(0)->after('rating');
            }
            if (! Schema::hasColumn('material_products', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('review_count')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('material_products', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_products', function (Blueprint $table) {
            foreach (['updated_by', 'created_by', 'category_id'] as $column) {
                if (Schema::hasColumn('material_products', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['image_path', 'estimated_delivery_time', 'rating', 'review_count'] as $column) {
                if (Schema::hasColumn('material_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
