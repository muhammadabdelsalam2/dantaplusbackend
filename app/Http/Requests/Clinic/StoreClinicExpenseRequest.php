<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicExpenseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('assigned_to') && ! $this->has('assigned_to_user_id')) {
            $this->merge(['assigned_to_user_id' => $this->input('assigned_to')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'expense_category_id' => ['required_without:category', 'integer', 'exists:clinic_expense_categories,id'],
            'category' => ['required_without:expense_category_id', 'integer', 'exists:clinic_expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'expense_date' => ['required_without:date', 'date'],
            'date' => ['required_without:expense_date', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
