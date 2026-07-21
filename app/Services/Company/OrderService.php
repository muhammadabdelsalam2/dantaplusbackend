<?php

namespace App\Services\Company;

use App\Http\Resources\Company\OrderResource;
use App\Models\Clinic;
use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\MaterialProduct;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Services\Clinic\WhatsappBot\Providers\MetaWhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
                    $product = ! empty($item['product_id'])
                        ? $this->resolveExternalOrderProduct($item, $order->company_id)
                        : null;

                    if (! empty($item['product_id']) && ! $product) {
                        throw ValidationException::withMessages([
                            'items' => ['Selected material product was not found for this company.'],
                        ]);
                    }

                    $unitPrice = $product ? (float) $product->price : (float) ($item['unit_price'] ?? 0);
                    $quantity = (int) $item['quantity'];

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product?->id ?? ($item['product_id'] ?? null),
                        'item_name' => $product?->name ?? $item['item_name'],
                        'category' => $product?->category ?? ($item['category'] ?? null),
                        'unit' => $item['unit'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $quantity * $unitPrice,
                    ]);
                }

                $subtotal = $order->items()->sum('line_total');
                $total = $subtotal + (float) ($order->shipping_cost ?? 0);
                $order->update(['total_amount' => $total, 'amount_total' => $total]);
            }

            return $this->show($order->fresh());
        });
    }

    public function updateStatus(Order $order, string $status): array
    {
        return DB::transaction(function () use ($order, $status) {
            $fromStatus = $order->status;

            if ($fromStatus !== $status) {
                $order->update(['status' => $status]);
                $this->recordStatusHistory($order, $fromStatus, $status);
            }

            return $this->show($order->fresh());
        });
    }
public function clinicsFilterOptions(): array
{
    return Order::query()
        ->where('company_id', auth()->user()->company_id)
        ->whereNotNull('clinic_id')
        ->with('clinic:id,name,phone')
        ->get()
        ->pluck('clinic')
        ->filter()
        ->unique('id')
        ->values()
        ->map(fn ($clinic) => ['id' => $clinic->id, 'name' => $clinic->name, 'phone' => $clinic->phone])
        ->all();
}

    public function complete(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $fromStatus = $order->status;
            $order->update([
                'status' => \App\Enums\OrderStatus::COMPLETED,
                'payment_status' => $order->payment_status ?: 'Pending',
            ]);
            $this->recordStatusHistory($order, $fromStatus, \App\Enums\OrderStatus::COMPLETED);

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
        $clinic = !empty($data['clinic_id']) ? Clinic::find($data['clinic_id']) : null;
        $externalClinicName = $clinic?->name ?? $data['external_clinic_name'];
        $externalClinicPhone = $clinic?->phone ?? $data['external_clinic_phone'];

        $order = Order::create([
            'company_id' => auth()->user()->company_id,
            'supplier_company_id' => auth()->user()->company_id,
            'order_code' => 'EXT-' . now()->format('YmdHis'),
            'clinic_id' => $clinic?->id,
            'external_clinic_name' => $externalClinicName,
            'external_clinic_phone' => $externalClinicPhone,
            'status' => \App\Enums\OrderStatus::PENDING_SUPPLIER_CONFIRMATION,
            'notes' => $data['notes'] ?? null,
            'payment_method' => $data['payment_method'],
            'payment_status' => 'Pending',
            'source' => 'external',
            'delivery_address' => $data['delivery_address'],
            'delivery_at' => $data['delivery_at'],
            'shipping_cost' => $data['shipping_cost'] ?? 0,
            'created_by' => auth()->id(),
            'order_date' => now(),
            'total_amount' => 0,
            'amount_total' => 0,
        ]);

        foreach ($data['items'] as $item) {
            $product = $this->resolveExternalOrderProduct($item, $order->company_id);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => ['Selected material product was not found for this company.'],
                ]);
            }

            $unitPrice = (float) $product->price;
            $quantity = (int) $item['quantity'];

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'item_name' => $product->name,
                'category' => $product->category,
                'unit' => $item['unit'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ]);
        }

        $subtotal = $order->items()->sum('line_total');
        $order->update([
            'total_amount' => $subtotal + (float) ($order->shipping_cost ?? 0),
            'amount_total' => $subtotal + (float) ($order->shipping_cost ?? 0),
        ]);
        $freshOrder = $order->fresh(['clinic', 'items.product']);
        $orderData = $this->show($freshOrder);
        $orderData['whatsapp'] = $this->sendExternalOrderWhatsApp($freshOrder);

        return $orderData;
    });
}

public function printData(Order $order): array
{
    $order->loadMissing(['company', 'clinic', 'items.product', 'invoice']);
    $data = $this->show($order);
    $data['file_url'] = URL::route('company.orders.invoice.download', ['id' => $order->id]);
    $data['invoice'] = $this->orderInvoiceData($order);

    return $data;
}

public function history(Order $order): array
{
    return $order->statusHistories()
        ->with('changedBy:id,name,email')
        ->oldest('id')
        ->get()
        ->map(fn (OrderStatusHistory $history) => [
            'id' => $history->id,
            'changed_by' => $history->changedBy ? [
                'id' => $history->changedBy->id,
                'name' => $history->changedBy->name,
                'email' => $history->changedBy->email,
            ] : null,
            'from_status' => $history->from_status,
            'to_status' => $history->to_status,
            'changed_at' => optional($history->created_at)?->toISOString(),
        ])
        ->all();
}

public function downloadInvoicePdf(Order $order)
{
    $order->loadMissing(['company', 'clinic', 'items.product', 'invoice']);
    $pdf = Pdf::loadView('pdf.company-order-invoice', [
        'order' => $order,
        'invoiceNumber' => 'INV-' . $order->order_code,
    ]);

    return $pdf->download('invoice-' . $order->order_code . '.pdf');
}

public function sendExternalOrderWhatsApp(Order $order): array
{
    $order->loadMissing(['clinic', 'items.product']);
    $phone = $order->clinic?->phone ?? $order->external_clinic_phone;

    if (! $phone) {
        return [
            'success' => false,
            'provider' => 'meta',
            'message' => 'Clinic phone is missing.',
        ];
    }

    $message = $this->externalOrderWhatsAppMessage($order);
    $result = app(MetaWhatsAppService::class)->sendMessage($this->normalizeWhatsAppPhone($phone), $message, $order->clinic);

    Log::info('External order WhatsApp dispatch attempted.', [
        'order_id' => $order->id,
        'provider' => 'meta',
        'success' => $result['success'] ?? false,
        'status_code' => $result['status_code'] ?? null,
    ]);

    return $result;
}

private function recordStatusHistory(Order $order, ?string $fromStatus, string $toStatus): void
{
    if ($fromStatus === $toStatus) {
        return;
    }

    OrderStatusHistory::create([
        'order_id' => $order->id,
        'changed_by' => auth()->id(),
        'from_status' => $fromStatus,
        'to_status' => $toStatus,
    ]);
}

private function resolveExternalOrderProduct(array $item, int $companyId): ?MaterialProduct
{
    if (! empty($item['product_id'])) {
        return MaterialProduct::query()
            ->where('company_id', $companyId)
            ->find($item['product_id']);
    }

    return null;
}

private function orderInvoiceData(Order $order): array
{
    $subtotal = (float) $order->items->sum('line_total');
    $tax = $order->invoice ? (float) $order->invoice->tax : round($subtotal * 0.14, 2);
    $shipping = (float) ($order->shipping_cost ?? 0);

    return [
        'invoice_number' => 'INV-' . $order->order_code,
        'date' => optional($order->order_date ?? $order->created_at)->format('d/m/Y'),
        'order_id' => $order->order_code,
        'company' => [
            'name' => $order->company?->name,
            'address' => $order->company?->address,
            'phone' => $order->company?->phone,
            'email' => $order->company?->email,
            'logo_url' => $order->company?->logo_url,
        ],
        'bill_to' => [
            'name' => $order->clinic?->name ?? $order->external_clinic_name,
            'address' => $order->clinic?->address ?? $order->delivery_address,
            'phone' => $order->clinic?->phone ?? $order->external_clinic_phone,
        ],
        'items' => $order->items->map(fn (OrderItem $item) => [
            'item_name' => $item->item_name ?: $item->product?->name,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'total' => (float) $item->line_total,
        ])->values()->all(),
        'subtotal' => $subtotal,
        'tax' => $tax,
        'shipping' => $shipping,
        'total_amount_due' => $subtotal + $tax + $shipping,
    ];
}

private function externalOrderWhatsAppMessage(Order $order): string
{
    $lines = $order->items->map(
        fn (OrderItem $item) => '- ' . ($item->item_name ?: $item->product?->name) . ' x ' . $item->quantity . ' @ ' . number_format((float) $item->unit_price, 2)
    )->implode("\n");

    return "New external order {$order->order_code}\n"
        . "Status: {$order->status}\n"
        . "Items:\n{$lines}\n"
        . 'Shipping: ' . number_format((float) $order->shipping_cost, 2) . "\n"
        . 'Total: ' . number_format((float) $order->total_amount, 2) . "\n"
        . 'Expected delivery: ' . optional($order->delivery_at)->format('Y-m-d');
}

private function normalizeWhatsAppPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?: '';

    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    if (str_starts_with($digits, '01') && strlen($digits) === 11) {
        return '20' . substr($digits, 1);
    }

    return $digits;
}
}
