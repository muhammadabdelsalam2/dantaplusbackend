<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'amount' => (float) $this->amount,
            'expense_date' => optional($this->expense_date)?->toDateString(),
            'notes' => $this->notes,
            'receipt_url' => $this->receipt_path ? asset('storage/' . $this->receipt_path) : null,
        ];
    }
}
