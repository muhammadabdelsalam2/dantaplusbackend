<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialCategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = config('material_market.product_category_items', []);

        foreach ($items as $item) {
            DB::table('material_categories')->updateOrInsert(
                ['key' => $item['key']],
                ['label' => $item['label'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
