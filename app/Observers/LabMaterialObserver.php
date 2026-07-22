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

        if ($newStock >= $oldStock || ! $this->shouldNotify($newStock)) {
            return;
        }

        Notification::query()->create([
            'title' => 'Inventory Low Stock',
            'message' => "{$material->name} stock reached {$newStock}.",
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

    private function shouldNotify(int $stock): bool
    {
        return $stock === 10 || $stock <= 5;
    }
}
