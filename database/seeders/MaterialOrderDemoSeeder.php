<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\MaterialCompany;
use App\Models\MaterialOrder;
use App\Models\MaterialOrderItem;
use App\Models\MaterialProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaterialOrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $clinics = Clinic::query()->take(15)->get();

            if ($clinics->count() < 5) {
                $clinics = collect();

               $clinics = Clinic::query()->take(15)->get();

if ($clinics->count() < 5) {
    $needed = 10 - $clinics->count();

    for ($i = 1; $i <= $needed; $i++) {
        $clinics->push(Clinic::create([
            'name' => 'Clinic ' . ($clinics->count() + 1),
            'owner_name' => 'Dr. Owner ' . ($clinics->count() + 1),
            'email' => 'clinic' . uniqid() . '@example.com',
            'phone' => '010' . str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT),
        ]));
    }
}
            }

            $companies = MaterialCompany::query()->with('products')->take(10)->get();

            if ($companies->count() < 3) {
                $companies = collect();

                $companyCategoriesPool = [
                    ['restorative', 'prosthodontics'],
                    ['orthodontic'],
                    ['endodontics'],
                    ['implantology', 'surgical'],
                    ['prosthodontics', 'restorative'],
                ];

                for ($i = 1; $i <= 6; $i++) {
                    $company = MaterialCompany::create([
                        'name' => 'Material Company ' . $i,
                        'email' => 'material-company' . $i . '@example.com',
                        'commission_percentage' => rand(5, 18),
                        'logo_url' => null,
                        'description' => 'Demo supplier company ' . $i,
                        'phone' => '201100000' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                        'website' => 'https://company' . $i . '.example.com',
                        'country' => 'Egypt',
                        'city' => collect(['Cairo', 'Giza', 'Alexandria', 'Mansoura', 'Minya'])->random(),
                        'address' => 'Street ' . $i . ', Building ' . rand(1, 30),
                        'categories' => $companyCategoriesPool[array_rand($companyCategoriesPool)],
                        'status' => $i % 5 === 0 ? 'Inactive' : 'Active',
                        'is_featured' => $i % 2 === 0,
                        'rating' => rand(30, 50) / 10,
                    ]);

                    $products = [];
                    $productCategories = [
                        'Implants',
                        'Abutments',
                        'Composite',
                        'Burs',
                        'Cements',
                        'Endo Files',
                        'Orthodontic Wires',
                        'Sutures',
                    ];

                    for ($p = 1; $p <= 12; $p++) {
                        $products[] = MaterialProduct::create([
                            'company_id' => $company->id,
                            'name' => $company->name . ' Product ' . $p,
                            'category' => $productCategories[array_rand($productCategories)],
                            'sku' => strtoupper(Str::random(10)),
                            'description' => 'Demo material product ' . $p . ' for ' . $company->name,
                            'price' => rand(50, 1500),
                            'stock' => rand(20, 300),
                            'status' => rand(1, 10) > 2 ? 'Active' : 'Inactive',
                            'image_url' => null,
                        ]);
                    }

                    $company->setRelation('products', collect($products));
                    $companies->push($company);
                }
            } else {
                $companies->each(function ($company) {
                    if ($company->products->count() === 0) {
                        for ($p = 1; $p <= 8; $p++) {
                            MaterialProduct::create([
                                'company_id' => $company->id,
                                'name' => $company->name . ' Product ' . $p,
                                'category' => collect([
                                    'Implants',
                                    'Abutments',
                                    'Composite',
                                    'Burs',
                                    'Cements',
                                    'Endo Files',
                                ])->random(),
                                'sku' => strtoupper(Str::random(10)),
                                'description' => 'Demo material product ' . $p,
                                'price' => rand(50, 1200),
                                'stock' => rand(10, 250),
                                'status' => 'Active',
                                'image_url' => null,
                            ]);
                        }

                        $company->load('products');
                    }
                });
            }

            $statuses = MaterialOrder::STATUSES;
            $paymentMethods = ['Cash', 'Card', 'Bank Transfer', 'Wallet'];
            $paymentStatuses = ['Pending', 'Paid', 'Failed', 'Refunded'];

            for ($i = 1; $i <= 120; $i++) {
                $clinic = $clinics->random();
                $company = $companies->random();

                $products = MaterialProduct::query()
                    ->where('company_id', $company->id)
                    ->where('status', 'Active')
                    ->inRandomOrder()
                    ->take(rand(2, 6))
                    ->get();

                if ($products->isEmpty()) {
                    continue;
                }

                $commissionPercentage = (float) $company->commission_percentage;

                $order = MaterialOrder::create([
                    'order_code' => 'MO-' . now()->format('Ymd') . '-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'clinic_id' => $clinic->id,
                    'supplier_company_id' => $company->id,
                    'order_date' => Carbon::now()->subDays(rand(0, 120))->subHours(rand(0, 23)),
                    'amount_total' => 0,
                    'status' => $statuses[array_rand($statuses)],
                    'commission_amount' => 0,
                    'notes' => 'Demo order #' . $i,
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                    'payment_reference' => strtoupper(Str::random(12)),
                ]);

                $total = 0;

                foreach ($products as $product) {
                    $quantity = rand(1, 8);
                    $unitPrice = (float) $product->price;
                    $lineTotal = $quantity * $unitPrice;
                    $total += $lineTotal;

                    MaterialOrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]);
                }

                $commissionAmount = round($total * ($commissionPercentage / 100), 2);

                $order->update([
                    'amount_total' => round($total, 2),
                    'commission_amount' => $commissionAmount,
                ]);
            }
        });
    }
}
