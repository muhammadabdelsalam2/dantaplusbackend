<?php

namespace App\Services\Company;

use App\Http\Resources\Company\InventoryResource;
use App\Models\InventoryItem;
use App\Models\InventoryLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventoryService
{
    public function paginate(array $filters): array
    {
        $items = InventoryItem::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('product_name', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 100)));

        return [
            'items' => InventoryResource::collection($items->items())->resolve(),
            'meta' => ['page' => $items->currentPage(), 'per_page' => $items->perPage(), 'total' => $items->total()],
            'low_stock_count' => InventoryItem::query()->whereColumn('quantity', '<=', 'minimum_stock_level')->count(),
        ];
    }

    public function show(InventoryItem $item): array
    {
        return (new InventoryResource($item))->resolve();
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $data['company_id'] = auth()->user()->company_id;
            $data['image_path'] = $this->storeImage($data['image'] ?? null);
            $data['last_updated_at'] = now();
            unset($data['image']);
            $item = InventoryItem::create($data);
            $this->log($item, 'create', $item->quantity, 'Initial stock');
            return $this->show($item);
        });
    }

    public function update(InventoryItem $item, array $data): array
    {
        return DB::transaction(function () use ($item, $data) {
            if (($data['image'] ?? null) instanceof UploadedFile) {
                if ($item->image_path) {
                    Storage::disk('public')->delete($item->image_path);
                }
                $data['image_path'] = $this->storeImage($data['image']);
            }

            unset($data['image']);
            $data['last_updated_at'] = now();
            $item->update($data);
            return $this->show($item->fresh());
        });
    }

    public function delete(InventoryItem $item): void
    {
        $item->delete();
    }

    public function adjust(InventoryItem $item, array $data): array
    {
        return DB::transaction(function () use ($item, $data) {
            $delta = $data['action'] === 'increase' ? $data['amount'] : -$data['amount'];
            $item->update([
                'quantity' => max(0, $item->quantity + $delta),
                'status' => ($item->quantity + $delta) <= $item->minimum_stock_level ? 'low_stock' : 'active',
                'last_updated_at' => now(),
            ]);
            $this->log($item->fresh(), $data['action'], $data['amount'], $data['reason'] ?? null);
            return $this->show($item->fresh());
        });
    }

    public function logs(InventoryItem $item): array
    {
        return $item->logs()->latest('created_at')->get()->map(fn ($log) => [
            'id' => $log->id,
            'action' => $log->action,
            'amount' => $log->amount,
            'reason' => $log->reason,
            'user_id' => $log->user_id,
            'created_at' => optional($log->created_at)?->toISOString(),
        ])->all();
    }

    private function log(InventoryItem $item, string $action, int $amount, ?string $reason): void
    {
        InventoryLog::create([
            'inventory_item_id' => $item->id,
            'company_id' => $item->company_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'amount' => $amount,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function storeImage(?UploadedFile $image): ?string
    {
        return $image ? $image->store('company/inventory', 'public') : null;
    }
}
