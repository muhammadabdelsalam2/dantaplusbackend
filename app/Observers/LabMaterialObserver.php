<?php

namespace App\Observers;

use App\Models\LabMaterial;
use App\Models\Notification;

class LabMaterialObserver
{
    public function updated(LabMaterial $material): void
    {
        if (! $material->wasChanged('stock')) {
            return;
        }

        $oldStock = (int) $material->getOriginal('stock');
        $newStock = (int) $material->stock;

        if ($newStock >= $oldStock || ! $this->shouldNotify($oldStock, $newStock)) {
            return;
        }

        Notification::query()->create([
            'title' => 'Inventory Low Stock',
            'message' => $this->message($material->name, $newStock),
            'type' => 'inventory_low_stock',
            'status' => 'Sent',
            'audience_type' => 'lab',
            'audience_id' => $material->lab_id,
            'priority' => $newStock <= 5 ? 'Urgent' : 'Normal',
            'delivery_methods' => ['system'],
            'is_read' => false,
            'link' => '/lab/inventories',
        ]);
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
