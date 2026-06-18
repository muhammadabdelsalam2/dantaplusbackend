<?php

namespace App\Http\Resources\Lab\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_invoice_id' => $this->lab_invoice_id,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'transaction_reference' => $this->transaction_reference,
            'paid_at' => optional($this->paid_at)?->toISOString(),
            'notes' => $this->notes,
            'recorded_by' => $this->recorder ? [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null,
        ];
    }
}
