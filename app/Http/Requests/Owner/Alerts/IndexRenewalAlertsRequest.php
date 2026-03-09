<?php

namespace App\Http\Requests\Owner\Alerts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRenewalAlertsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['expiring_soon', 'overdue_payments', 'recently_renewed'])],
            'search' => ['nullable', 'string', 'max:255'],
            'within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
