<?php

namespace App\Http\Requests\Lab\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lab_expense_category_id' => ['nullable', 'integer', 'exists:lab_expense_categories,id'],
            'expense_type' => ['nullable', 'in:Materials,Salaries,Utilities,Maintenance,Delivery,Other'],
            'type' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'expense_date' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:4096'],
        ];
    }
}
