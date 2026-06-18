<?php

namespace App\Http\Resources\Lab\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabInvoiceItemMaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_material_id' => $this->lab_material_id,
            'material_name' => $this->material_name,
            'material_type' => $this->material_type,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
        ];
    }
}
