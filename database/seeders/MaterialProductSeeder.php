<?php

namespace Database\Seeders;

use App\Models\MaterialCompany;
use App\Models\MaterialProduct;
use Illuminate\Database\Seeder;

class MaterialProductSeeder extends Seeder
{
    public function run(): void
    {
        $companies = MaterialCompany::all();

        foreach ($companies as $company) {
            for ($i = 1; $i <= 15; $i++) {
                MaterialProduct::create([
                    'company_id' => $company->id,
                    'name' => $company->name . ' Product ' . $i,
                    'category' => collect(['Implants', 'Tools', 'Consumables', 'Equipment'])->random(),
                    'price' => rand(100, 3000),
                    'image_url' => null,
                ]);
            }
        }
    }
}
