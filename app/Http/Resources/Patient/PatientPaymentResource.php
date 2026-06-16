<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'invoice_id' => $this->clinic_invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'paid_at' => optional($this->paid_at)?->toISOString(),
            'notes' => $this->notes,
            'invoice' => $this->invoice ? [
                'id' => $this->invoice->id,
                'total' => (float) $this->invoice->total,
                'paid' => (float) $this->invoice->paid,
                'remaining' => (float) $this->invoice->remaining,
                'status' => $this->invoice->status,
            ] : null,
        ], static fn ($value) => $value !== null);
    }
}
