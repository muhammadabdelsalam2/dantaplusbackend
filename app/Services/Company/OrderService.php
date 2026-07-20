<?php

namespace App\Services\Company;

use App\Http\Resources\Company\OrderResource;
use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function paginate(array $filters, ?string $source = null): array
    {
        $orders = Order::query()
            ->with(['clinic:id,name,email,phone', 'invoice:id,order_id', 'items'])
            ->when($source, fn ($q) => $q->where('source', $source))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
             ->when($filters['clinic_id'] ?? null, fn ($q, $clinicId) => $q->where('clinic_id', $clinicId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('order_code', 'like', "%{$search}%")
                        ->orWhere('external_clinic_name', 'like', "%{$search}%")
                        ->orWhereHas('clinic', fn ($clinic) => $clinic->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 100)));

        return [
            'items' => OrderResource::collection($orders->items())->resolve(),
            'meta' => ['page' => $orders->currentPage(), 'per_page' => $orders->perPage(), 'total' => $orders->total()],
        ];
    }

    public function show(Order $order): array
    {
        $order->load(['clinic:id,name,email,phone', 'items.product:id,name', 'invoice']);
        return (new OrderResource($order))->resolve();
    }

    public function update(Order $order, array $data): array
    {
        return DB::transaction(function () use ($order, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);
            $order->update($data);

            if (is_array($items)) {
                $order->items()->delete();
                foreach ($items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'] ?? null,
                        'item_name' => $item['item_name'],
                        'unit' => $item['unit'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'line_total' => $item['quantity'] * $item['unit_price'],
                    ]);
                }

                $order->update(['total_amount' => $order->items()->sum('line_total'), 'amount_total' => $order->items()->sum('line_total')]);
            }

            return $this->show($order->fresh());
        });
    }

    public function updateStatus(Order $order, string $status): array
    {
        $order->update(['status' => $status]);
        return $this->show($order->fresh());
    }
    public function clinicsFilterOptions(): array
{
    return Order::query()
        ->where('company_id', auth()->user()->company_id)
        ->whereNotNull('clinic_id')
        ->with('clinic:id,name')
        ->get()
        ->pluck('clinic')
        ->filter()
        ->unique('id')
        ->values()
        ->map(fn ($clinic) => ['id' => $clinic->id, 'name' => $clinic->name])
        ->all();
}

    public function complete(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'Delivered',
                'payment_status' => $order->payment_status ?: 'Pending',
            ]);

            $invoice = Invoice::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'company_id' => $order->company_id,
                    'clinic_id' => $order->clinic_id,
                    'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(14)->toDateString(),
                    'subtotal' => $order->items()->sum('line_total'),
                    'tax' => 0,
                    'total_amount' => $order->items()->sum('line_total'),
                    'status' => 'unpaid',
                    'payment_method' => $order->payment_method,
                    'completion_date' => now(),
                    'order_type' => $order->source,
                ]
            );

            return ['order' => $this->show($order->fresh()), 'invoice_id' => $invoice->id];
        });
    }

    public function communicationLogs(Order $order): array
    {
        return Message::query()
            ->where('company_id', $order->company_id)
            ->where('related_type', 'order')
            ->where('related_id', $order->id)
            ->latest('id')
            ->get()
            ->map(fn ($message) => [
                'id' => $message->id,
                'sender_name' => $message->sender_name,
                'message_type' => $message->message_type,
                'content' => $message->content,
                'attachment_url' => $message->attachment_path ? asset('storage/' . $message->attachment_path) : null,
                'created_at' => optional($message->created_at)?->toISOString(),
            ])
            ->all();
    }

    public function createExternal(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'company_id' => auth()->user()->company_id,
                'supplier_company_id' => auth()->user()->company_id,
                'order_code' => 'EXT-' . now()->format('YmdHis'),
                'external_clinic_name' => $data['external_clinic_name'],
                'external_clinic_phone' => $data['external_clinic_phone'],
                'status' => 'Pending',
                'notes' => $data['notes'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_status' => $data['payment_status'] ?? 'Pending',
                'source' => 'external',
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_at' => $data['delivery_at'] ?? null,
                'created_by' => auth()->id(),
                'order_date' => now(),
                'total_amount' => 0,
                'amount_total' => 0,
            ]);

            foreach ($data['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'unit' => $item['unit'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            $order->update(['total_amount' => $order->items()->sum('line_total'), 'amount_total' => $order->items()->sum('line_total')]);
            return $this->show($order->fresh());
        });
    }
}
