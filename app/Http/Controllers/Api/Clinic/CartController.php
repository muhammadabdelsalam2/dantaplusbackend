<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MaterialProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    use ApiResponse;

    public function show()
    {
        if (! auth()->user()?->clinic_id) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $cart = $this->activeCart();

        return ApiResponse::success($this->formatCart($cart), 'Cart fetched successfully');
    }

    public function storeItem(Request $request)
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $validated = $request->validate([
            'material_product_id' => ['required', 'integer', 'exists:material_products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $material = MaterialProduct::query()
            ->with('company:id,name,status')
            ->whereIn('status', ['Active', 'active'])
            ->find($validated['material_product_id']);

        if (! $material) {
            return ApiResponse::error('Material not found.', 422, ['material_product_id' => ['Material not found.']]);
        }

        $cart = $this->activeCart();

        $item = CartItem::query()->updateOrCreate(
            [
                'cart_id' => $cart->id,
                'material_product_id' => $material->id,
            ],
            [
                'quantity' => (int) $validated['quantity'],
                'unit_price' => $material->price,
                'line_total' => round((int) $validated['quantity'] * (float) $material->price, 2),
            ]
        );

        return ApiResponse::success($this->formatCart($cart->fresh(['items.material.company'])), 'Cart item saved successfully', $item->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyItem(int $id)
    {
        $cart = $this->activeCart();
        $item = $cart->items()->whereKey($id)->first();

        if (! $item) {
            return ApiResponse::error('Cart item not found.', 404);
        }

        $item->delete();

        return ApiResponse::success($this->formatCart($cart->fresh(['items.material.company'])), 'Cart item deleted successfully');
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash'],
            'notes' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = $this->activeCart();
        $cart->load(['items.material.company']);

        if ($cart->items->isEmpty()) {
            return ApiResponse::error('Cart is empty.', 422, ['cart' => ['Cart is empty.']]);
        }

        $orders = DB::transaction(function () use ($cart, $validated) {
            return $cart->items
                ->groupBy(fn (CartItem $item) => $item->material?->company_id)
                ->map(function ($items, $supplierId) use ($cart, $validated) {
                    $total = round((float) $items->sum('line_total'), 2);
                    $order = Order::query()->create([
                        'order_code' => 'ORD-CART-' . now()->format('YmdHis') . '-' . $supplierId,
                        'clinic_id' => $cart->clinic_id,
                        'supplier_company_id' => (int) $supplierId,
                        'company_id' => (int) $supplierId,
                        'order_date' => now(),
                        'amount_total' => $total,
                        'total_amount' => $total,
                        'status' => 'pending',
                        'notes' => $validated['notes'] ?? 'Checkout from clinic cart',
                        'payment_method' => 'cash',
                        'payment_status' => 'pending_cash',
                        'source' => 'clinic_cart',
                        'delivery_address' => $validated['delivery_address'] ?? null,
                        'created_by' => auth()->id(),
                    ]);

                    foreach ($items as $item) {
                        OrderItem::query()->create([
                            'order_id' => $order->id,
                            'product_id' => $item->material_product_id,
                            'item_name' => $item->material?->name,
                            'unit' => 'piece',
                            'quantity' => $item->quantity,
                            'qty_original' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'line_total' => $item->line_total,
                        ]);
                    }

                    return $order->load(['supplierCompany:id,name', 'items.product:id,name']);
                })
                ->values();
        });

        $cart->update(['status' => Cart::STATUS_CHECKED_OUT]);

        return ApiResponse::success([
            'orders' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'order_id' => $order->order_code,
                'supplier' => $order->supplierCompany?->name,
                'total' => (float) ($order->total_amount ?? $order->amount_total),
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
            ])->values(),
        ], 'Cart checked out successfully', 201);
    }

    private function activeCart(): Cart
    {
        return Cart::query()->firstOrCreate(
            [
                'clinic_id' => auth()->user()?->clinic_id,
                'user_id' => auth()->id(),
                'status' => Cart::STATUS_ACTIVE,
            ],
            []
        )->load(['items.material.company']);
    }

    private function formatCart(Cart $cart): array
    {
        $cart->loadMissing(['items.material.company']);

        return [
            'id' => $cart->id,
            'clinic_id' => $cart->clinic_id,
            'user_id' => $cart->user_id,
            'status' => $cart->status,
            'items' => $cart->items->map(fn (CartItem $item) => [
                'id' => $item->id,
                'material_product_id' => $item->material_product_id,
                'name' => $item->material?->name,
                'company' => $item->material?->company ? [
                    'id' => $item->material->company->id,
                    'name' => $item->material->company->name,
                ] : null,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ])->values(),
            'total' => round((float) $cart->items->sum('line_total'), 2),
        ];
    }
}
