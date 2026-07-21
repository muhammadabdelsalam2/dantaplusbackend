<?php

namespace Database\Seeders;

use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\Clinic;
use App\Models\Company;
use App\Models\CompanyExpense;
use App\Models\CompanySetting;
use App\Models\Conversation;
use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SharedFile;
use App\Models\ShippingZone;
use App\Models\User;
use App\Enums\OrderStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SupplierPortalSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $categories = collect([
                ['name' => 'Impression Materials', 'slug' => 'impression-materials', 'status' => 'active'],
                ['name' => 'Endodontic Files', 'slug' => 'endodontic-files', 'status' => 'active'],
                ['name' => 'Dental Cements', 'slug' => 'dental-cements', 'status' => 'active'],
                ['name' => 'Composite Materials', 'slug' => 'composite-materials', 'status' => 'active'],
                ['name' => 'Surgical Consumables', 'slug' => 'surgical-consumables', 'status' => 'active'],
            ])->map(fn ($category) => Category::query()->updateOrCreate(['slug' => $category['slug']], $category));

            $company = Company::query()->updateOrCreate(
                ['email' => 'info@dentsply-eg.com'],
                [
                    'name' => 'Dentsply Sirona Egypt',
                    'description' => 'Production-ready supplier profile for the supplier portal demo.',
                    'phone' => '+20 123 456 7890',
                    'website' => 'https://dentsply-eg.example.com',
                    'country' => 'Egypt',
                    'city' => 'Cairo',
                    'address' => 'New Cairo, 5th Settlement, Industrial Zone',
                    'status' => 'Active',
                    'rating' => 4.8,
                    'commission_percentage' => 12,
                ]
            );

            $admin = User::query()->updateOrCreate(
                ['email' => 'admin@dentsply-eg.com'],
                [
                    'company_id' => $company->id,
                    'name' => 'Ahmed Hassan',
                    'username' => 'ahmedhassan',
                    'phone' => '+20 100 111 2222',
                    'password' => Hash::make('password'),
                    'role' => 'material_company_admin',
                    'status' => 'Active',
                    'is_active' => true,
                    'is_verified' => true,
                ]
            );
            $admin->syncRoles(['material_company_admin']);

            $sales = User::query()->updateOrCreate(
                ['email' => 'sales@dentsply-eg.com'],
                [
                    'company_id' => $company->id,
                    'name' => 'Company Sales Representative',
                    'username' => 'salesrep',
                    'phone' => '+20 100 111 3333',
                    'password' => Hash::make('password'),
                    'role' => 'sales_rep',
                    'status' => 'Active',
                    'is_active' => true,
                    'is_verified' => true,
                ]
            );
            $sales->syncRoles(['sales_rep']);

            $delivery = User::query()->updateOrCreate(
                ['email' => 'delivery@dentsply-eg.com'],
                [
                    'company_id' => $company->id,
                    'name' => 'Mostafa Samy',
                    'username' => 'mostafasamy',
                    'phone' => '+20 100 111 4444',
                    'password' => Hash::make('password'),
                    'role' => 'delivery_staff',
                    'status' => 'Active',
                    'is_active' => true,
                    'is_verified' => true,
                ]
            );
            $delivery->syncRoles(['delivery_staff']);

            $products = collect([
                ['name' => 'Aquasil Ultra+ Smart Wetting', 'brand' => 'Dentsply Sirona', 'price' => 850, 'stock' => 50, 'category_id' => $categories[0]->id],
                ['name' => 'ProTaper Gold Files', 'brand' => 'Dentsply Sirona', 'price' => 1125, 'stock' => 32, 'category_id' => $categories[1]->id],
                ['name' => 'Calibra Universal Cement', 'brand' => 'Dentsply Sirona', 'price' => 410, 'stock' => 80, 'category_id' => $categories[2]->id],
                ['name' => 'Ceram.X Spectra ST', 'brand' => 'Dentsply Sirona', 'price' => 620, 'stock' => 27, 'category_id' => $categories[3]->id],
            ])->map(function ($product) use ($company, $admin, $categories) {
                $category = $categories->firstWhere('id', $product['category_id']);
                return Product::query()->updateOrCreate(
                    ['company_id' => $company->id, 'name' => $product['name']],
                    array_merge($product, [
                        'description' => 'High-demand supplier portal sample product.',
                        'status' => 'active',
                        'estimated_delivery_time' => '24-48 hours',
                        'rating' => 4.5,
                        'review_count' => 18,
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                        'category' => $category?->name,
                    ])
                );
            });

            $products->each(function ($product) use ($company) {
                $inventory = InventoryItem::query()->updateOrCreate(
                    ['company_id' => $company->id, 'product_name' => $product->name],
                    [
                        'product_id' => $product->id,
                        'category_name' => $product->category,
                        'description' => $product->description,
                        'quantity' => $product->stock,
                        'minimum_stock_level' => 15,
                        'unit' => 'box',
                        'supplier' => 'Main warehouse',
                        'status' => $product->stock <= 15 ? 'low_stock' : 'active',
                        'last_updated_at' => now(),
                    ]
                );

                InventoryLog::query()->updateOrCreate(
                    ['inventory_item_id' => $inventory->id, 'action' => 'seed'],
                    ['company_id' => $company->id, 'user_id' => null, 'amount' => $inventory->quantity, 'reason' => 'Seeder bootstrap', 'created_at' => now()]
                );
            });

            $clinics = Clinic::query()->take(3)->get();
            if ($clinics->count() < 3) {
                for ($i = $clinics->count() + 1; $i <= 3; $i++) {
                    $clinics->push(Clinic::create([
                        'name' => 'Elite Dental Clinic ' . $i,
                        'owner_name' => 'Dr. Clinic ' . $i,
                        'email' => 'elite' . $i . '@clinic.test',
                        'phone' => '+20 155 000 00' . $i,
                        'address' => 'Cairo District ' . $i,
                        'subscription_plan' => 'Premium',
                        'payment_method' => 'Manual',
                        'status' => 'Active',
                        'start_date' => now()->subMonths(2),
                        'expiry_date' => now()->addYear(),
                        'max_users' => 10,
                        'max_branches' => 3,
                    ]));
                }
            }

            $clinics->values()->each(function ($clinic, $index) use ($company, $products, $admin) {
                $order = Order::query()->updateOrCreate(
                    ['order_code' => 'SP-' . now()->format('Ymd') . '-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                    [
                        'company_id' => $company->id,
                        'supplier_company_id' => $company->id,
                        'clinic_id' => $clinic->id,
                        'status' => $index === 0 ? OrderStatus::PROCESSING : ($index === 1 ? OrderStatus::DELIVERED : OrderStatus::PENDING_SUPPLIER_CONFIRMATION),
                        'notes' => 'Seeded supplier portal order',
                        'total_amount' => 0,
                        'amount_total' => 0,
                        'payment_method' => 'Bank Transfer',
                        'payment_status' => $index === 1 ? 'Paid' : 'Pending',
                        'source' => 'online',
                        'delivery_address' => $clinic->address,
                        'delivery_at' => now()->addDays($index + 1),
                        'created_by' => $admin->id,
                        'order_date' => now()->subDays($index * 4),
                    ]
                );

                $selectedProducts = $products->slice(0, 2 + ($index % 2));
                $order->items()->delete();
                foreach ($selectedProducts as $product) {
                    $qty = 2 + $index;
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'item_name' => $product->name,
                        'unit' => 'box',
                        'quantity' => $qty,
                        'unit_price' => $product->price,
                        'line_total' => $qty * $product->price,
                    ]);
                }

                $total = $order->items()->sum('line_total');
                $order->update(['total_amount' => $total, 'amount_total' => $total]);

                $invoice = Invoice::query()->updateOrCreate(
                    ['invoice_number' => 'INV-SP-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                    [
                        'order_id' => $order->id,
                        'company_id' => $company->id,
                        'clinic_id' => $clinic->id,
                        'issue_date' => now()->subDays($index)->toDateString(),
                        'due_date' => now()->addDays(15 - $index)->toDateString(),
                        'subtotal' => $total,
                        'tax' => round($total * 0.14, 2),
                        'total_amount' => round($total * 1.14, 2),
                        'status' => $order->payment_status === 'Paid' ? 'paid' : 'issued',
                        'payment_method' => $order->payment_method,
                        'completion_date' => $order->payment_status === 'Paid' ? now() : null,
                        'order_type' => $order->source,
                    ]
                );

                if ($order->payment_status === 'Paid') {
                    Payment::query()->updateOrCreate(
                        ['invoice_id' => $invoice->id, 'transaction_id' => 'TXN-SP-' . $invoice->id],
                        [
                            'company_id' => $company->id,
                            'amount' => $invoice->total_amount,
                            'method' => 'Bank Transfer',
                            'status' => 'paid',
                            'paid_at' => now()->subDay(),
                            'source' => 'seed',
                        ]
                    );
                }

                $conversation = Conversation::query()->updateOrCreate(
                    ['company_id' => $company->id, 'clinic_id' => $clinic->id, 'context_type' => 'order', 'context_id' => $order->id],
                    ['last_message_text' => 'Order inquiry', 'last_message_at' => now(), 'last_message_sender_id' => $admin->id]
                );

                Message::query()->updateOrCreate(
                    ['conversation_id' => $conversation->id, 'company_id' => $company->id, 'sender_name' => 'Ahmed Hassan', 'related_id' => $order->id],
                    [
                        'sender_type' => 'company_user',
                        'sender_id' => $admin->id,
                        'message_type' => 'text',
                        'content' => 'Your order is being prepared and scheduled for dispatch.',
                        'related_type' => 'order',
                        'text' => 'Your order is being prepared and scheduled for dispatch.',
                        'type' => 'text',
                        'is_read' => true,
                    ]
                );
            });

            CompanyExpense::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Warehouse Rent'],
                ['category' => 'operations', 'amount' => 12000, 'expense_date' => now()->startOfMonth()->toDateString(), 'notes' => 'Main warehouse monthly rent']
            );

            CompanyExpense::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Courier Fleet Fuel'],
                ['category' => 'logistics', 'amount' => 4500, 'expense_date' => now()->subDays(4)->toDateString(), 'notes' => 'Distribution fuel costs']
            );

            BankTransaction::query()->updateOrCreate(
                ['transaction_id' => 'BANK-SP-0001'],
                ['company_id' => $company->id, 'transaction_date' => now()->toDateString(), 'amount' => 8540.75, 'source' => 'CIB', 'status' => 'matched', 'type' => 'credit']
            );

            $conversation = Conversation::query()->where('company_id', $company->id)->first();
            if ($conversation) {
                SharedFile::query()->updateOrCreate(
                    ['conversation_id' => $conversation->id, 'company_id' => $company->id, 'file_name' => 'price-list-april.pdf'],
                    ['file_type' => 'application/pdf', 'file_path' => 'seed/price-list-april.pdf', 'uploaded_by_type' => 'company_user', 'uploaded_by_id' => $admin->id, 'uploaded_by_name' => $admin->name]
                );
            }

            ShippingZone::query()->updateOrCreate(
                ['company_id' => $company->id, 'zone_name' => 'Greater Cairo'],
                ['shipping_cost' => 120, 'estimated_delivery_time' => 'Same day', 'polygon_coordinates' => [['lat' => 30.0444, 'lng' => 31.2357]], 'is_active' => true, 'notes' => 'Fast-track zone']
            );

            ShippingZone::query()->updateOrCreate(
                ['company_id' => $company->id, 'zone_name' => 'Alexandria'],
                ['shipping_cost' => 180, 'estimated_delivery_time' => '24 hours', 'polygon_coordinates' => [['lat' => 31.2001, 'lng' => 29.9187]], 'is_active' => true, 'notes' => 'North coast logistics route']
            );

            CompanySetting::query()->updateOrCreate(
                ['company_id' => $company->id],
                [
                    'profile' => ['display_name' => $company->name, 'support_email' => $company->email],
                    'communication' => ['whatsapp_enabled' => true, 'provider' => 'meta_cloud_api', 'logs' => [['message' => 'WhatsApp connection verified', 'at' => now()->toISOString()]]],
                    'automation' => [
                        'auto_transfer_to_payments' => false,
                        'auto_create_invoice_billing' => true,
                        'whatsapp_notification_clinic' => false,
                        'auto_pdf_generation' => false,
                    ],
                ]
            );
        });
    }
}
