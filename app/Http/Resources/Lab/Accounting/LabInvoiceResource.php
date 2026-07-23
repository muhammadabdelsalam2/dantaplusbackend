<?php

namespace App\Http\Resources\Lab\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayStatus = match ($this->status) {
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'disputed' => 'Disputed',
            default => 'Pending',
        };

        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_number,
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
            'clinic_name' => $this->clinic?->name,
            'issue_date' => optional($this->issue_date)?->toDateString(),
            'issue_date_display' => optional($this->issue_date)?->format('d/m/Y'),
            'due_date' => optional($this->due_date)?->toDateString(),
            'amount' => (float) $this->total_amount,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'outstanding_amount' => (float) $this->remaining_amount,
            'status' => $displayStatus,
            'status_value' => $this->status,
            'notes' => $this->notes,
            'items' => LabInvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => LabPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
