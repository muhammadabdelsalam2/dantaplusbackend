<?php

namespace App\Http\Resources\Lab\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'date' => optional($this->expense_date)?->toDateString(),
            'type' => $this->category?->name,
            'description' => $this->notes ?? $this->title,
            'title' => $this->title,
            'amount' => (float) $this->amount,
            'amount_display' => '-$' . number_format((float) $this->amount, 2),
            'payment_method' => $this->payment_method,
            'expense_date' => optional($this->expense_date)?->toDateString(),
            'vendor' => $this->vendor,
            'notes' => $this->notes,
            'attachment_path' => $this->attachment_path,
        ];
    }
}
