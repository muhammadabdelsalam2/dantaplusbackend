<?php

namespace App\Http\Resources\Lab\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'lab_id' => $this->lab_id,
            'clinic' => $this->clinic ? [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
                'email' => $this->clinic->email,
                'phone' => $this->clinic->phone,
            ] : null,
            'doctor' => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->user?->name,
            ] : null,
            'period_month' => optional($this->period_month)?->format('Y-m'),
            'group_by' => $this->group_by,
            'issue_date' => optional($this->issue_date)?->toDateString(),
            'due_date' => optional($this->due_date)?->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'outstanding_amount' => (float) $this->remaining_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'items' => LabInvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => LabPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
