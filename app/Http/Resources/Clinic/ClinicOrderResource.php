<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : null;

        return [
            'id' => $this->id,
            'order_id' => $this->order_code,
            'clinic_id' => $this->clinic_id,
            'date' => optional($this->order_date)?->toISOString(),
            'payment' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'total' => (float) ($this->total_amount ?? $this->amount_total),
            'status' => $this->status,
            'supplier_note' => $this->supplier_note,
            'modified_by_supplier' => (bool) $this->modified_by_supplier,
            'supplier' => $this->supplierCompany?->name,
            'items' => $items?->map(function ($item) {
                $hasSupplierChange = $item->qty_modified !== null
                    && (int) $item->qty_modified !== (int) ($item->qty_original ?? $item->quantity);

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->item_name ?? $item->product?->name,
                    'qty' => (int) $item->quantity,
                    'qty_original' => (int) ($item->qty_original ?? $item->quantity),
                    'qty_modified' => $item->qty_modified !== null ? (int) $item->qty_modified : null,
                    'unit' => $item->unit,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                    'has_supplier_change' => $hasSupplierChange,
                ];
            })->values(),
            'review_changes' => $items?->filter(
                fn ($item) => $item->qty_modified !== null
                    && (int) $item->qty_modified !== (int) ($item->qty_original ?? $item->quantity)
            )->map(fn ($item) => [
                'item_id' => $item->id,
                'product_name' => $item->item_name ?? $item->product?->name,
                'old_qty' => (int) ($item->qty_original ?? $item->quantity),
                'new_qty' => (int) $item->qty_modified,
            ])->values(),
        ];
    }
}
