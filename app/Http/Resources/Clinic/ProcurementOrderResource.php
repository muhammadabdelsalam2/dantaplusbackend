<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcurementOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'clinic_id' => $this->clinic_id,
            'material_id' => $this->material_id,
            'material' => $this->material?->name,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier?->name ?? $this->supplier_name,
            'qty' => (int) $this->qty,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
            'status' => $this->status,
            'notes' => $this->notes,
            'ordered_at' => optional($this->ordered_at)?->toISOString(),
            'received_at' => optional($this->received_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
