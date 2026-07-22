<?php

namespace App\Observers;

use App\Models\MaterialProduct;
use App\Models\Notification;

class MaterialProductObserver
{
    /**
     * Handle the MaterialProduct "created" event.
     */
 
public function created(MaterialProduct $product): void
{
    if (empty($product->barcode)) {
        $product->updateQuietly([
            'barcode' => '200' . str_pad($product->id, 4, '0', STR_PAD_LEFT) . strtoupper(\Illuminate\Support\Str::random(6)),
        ]);
    }
}

    /**
     * Handle the MaterialProduct "updated" event.
     */
    public function updated(MaterialProduct $materialProduct): void
    {
        if (! $materialProduct->wasChanged('stock')) {
            return;
        }

        $oldStock = (int) $materialProduct->getOriginal('stock');
        $newStock = (int) $materialProduct->stock;

        if ($newStock >= $oldStock || ! $this->shouldNotify($oldStock, $newStock)) {
            return;
        }

        Notification::query()->create([
            'title' => 'Inventory Low Stock',
            'message' => $this->message($materialProduct->name, $newStock),
            'type' => 'inventory_low_stock',
            'status' => 'Sent',
            'audience_type' => 'supplier',
            'audience_id' => $materialProduct->company_id,
            'priority' => $newStock <= 5 ? 'Urgent' : 'Normal',
            'delivery_methods' => ['system'],
            'is_read' => false,
            'link' => '/company/inventory',
        ]);
    }

    /**
     * Handle the MaterialProduct "deleted" event.
     */
    public function deleted(MaterialProduct $materialProduct): void
    {
        //
    }

    /**
     * Handle the MaterialProduct "restored" event.
     */
    public function restored(MaterialProduct $materialProduct): void
    {
        //
    }

    /**
     * Handle the MaterialProduct "force deleted" event.
     */
    public function forceDeleted(MaterialProduct $materialProduct): void
    {
        //
    }

    private function shouldNotify(int $oldStock, int $newStock): bool
    {
        return ($oldStock > 10 && $newStock <= 10)
            || ($oldStock > 5 && $newStock <= 5)
            || ($oldStock <= 5 && $newStock < $oldStock);
    }

    private function message(string $name, int $stock): string
    {
        if ($stock <= 5) {
            return "{$name} stock is critically low ({$stock}).";
        }

        return "{$name} stock is running low ({$stock}).";
    }
}
