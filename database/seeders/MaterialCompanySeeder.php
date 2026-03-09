<?php

namespace Database\Seeders;

use App\Models\MaterialCompany;
use Illuminate\Database\Seeder;

class MaterialCompanySeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            MaterialCompany::create([
                'name' => 'Supplier Company ' . $i,
                'email' => 'supplier' . $i . '@example.com',
                'phone' => '0111111' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
            ]);
        }
    }
}
