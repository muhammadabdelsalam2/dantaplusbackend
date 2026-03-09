<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\MaterialCompany;
use App\Models\MaterialOrder;
use App\Models\MaterialOrderItem;
use App\Models\MaterialProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MaterialOrderSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = Clinic::query()->pluck('id')->toArray();
        $companies = MaterialCompany::query()->pluck('id')->toArray();

        if (empty($clinics) || empty($companies)) {
            $this->command->warn('Clinics or Material Companies are missing. Seed them first.');
            return;
        }

        for ($i = 1; $i <= 100; $i++) {
            $clinicId = $clinics[array_rand($clinics)];
            $companyId = $companies[array_rand($companies)];

            $products = MaterialProduct::query()
                ->where('company_id', $companyId)
                ->inRandomOrder()
                ->take(rand(2, 6))
                ->get();

            if ($products->isEmpty()) {
                continue;
            }

            $order = MaterialOrder::create([
                'order_code' => 'MO-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'clinic_id' => $clinicId,
                'supplier_company_id' => $companyId,
                'order_date' => Carbon::now()->subDays(rand(0, 120)),
                'amount_total' => 0,
                'status' => collect(MaterialOrder::STATUSES)->random(),
                'commission_amount' => rand(50, 500),
                'notes' => fake()->sentence(),
                'payment_method' => collect(['Cash', 'Card', 'Bank Transfer', 'Wallet'])->random(),
                'payment_status' => collect(['Pending', 'Paid', 'Partial'])->random(),
                'payment_reference' => strtoupper(Str::random(10)),
            ]);

            $total = 0;

            foreach ($products as $product) {
                $qty = rand(1, 10);
                $unitPrice = $product->price ?? rand(100, 2000);
                $lineTotal = $qty * $unitPrice;

                MaterialOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $order->update([
                'amount_total' => $total,
            ]);
        }
    }
}
