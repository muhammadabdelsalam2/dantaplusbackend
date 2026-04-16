<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:50',
            'status' => 'required|string|max:30',
            'transaction_id' => 'nullable|string|max:255',
            'paid_at' => 'nullable|date',
            'source' => 'nullable|string|max:255',
        ];
    }
}
