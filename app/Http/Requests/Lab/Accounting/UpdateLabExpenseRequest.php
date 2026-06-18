<?php

namespace App\Http\Requests\Lab\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lab_expense_category_id' => ['sometimes', 'integer', 'exists:lab_expense_categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'expense_date' => ['sometimes', 'date'],
            'vendor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'attachment' => ['sometimes', 'nullable', 'file', 'max:4096'],
        ];
    }
}
