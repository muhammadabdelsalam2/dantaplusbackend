<?php

namespace App\Http\Resources\Lab\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabInvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'case_number' => $this->case_number,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient_name,
            'service' => [
                'id' => $this->lab_service_id,
                'name' => $this->service_name,
            ],
            'technician' => $this->technician ? [
                'id' => $this->technician->id,
                'name' => $this->technician->name,
                'commission_rates' => $this->technician->commission_rates,
            ] : null,
            'teeth_numbers' => $this->teeth_numbers ?? [],
            'fdi_teeth_numbers' => $this->fdi_teeth_numbers ?? [],
            'dental_chart' => $this->dental_chart ?? [],
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'materials_cost' => (float) $this->materials_cost,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'materials' => LabInvoiceItemMaterialResource::collection($this->whenLoaded('materials')),
        ];
    }
}
