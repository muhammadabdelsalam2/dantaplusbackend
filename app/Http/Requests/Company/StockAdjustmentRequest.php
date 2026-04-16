<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'action' => 'required|in:increase,decrease',
            'amount' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
