<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'issue_date' => optional($this->issue_date)?->toDateString(),
            'due_date' => optional($this->due_date)?->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'file_url' => URL::route(
                'company.invoices.download.signed',
                ['id' => $this->id]
            ),
            'total_amount' => (float) $this->total_amount,
            'payment_method' => $this->payment_method,
            'completion_date' => optional($this->completion_date)?->toISOString(),
            'order_type' => $this->order_type,
            'order' => $this->order ? [
                'id' => $this->order->id,
                'order_code' => $this->order->order_code,
            ] : null,
            'clinic' => $this->clinic ? [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
            ] : null,
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
