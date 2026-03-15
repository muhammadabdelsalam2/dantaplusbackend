<?php

namespace App\Http\Requests\Lab\DeliveryReport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDeliveryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'period' => $this->input('period', 'this_month'),
        ]);
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in([
                'this_month',
                'last_30_days',
                'last_3_months',
            ])],
        ];
    }
}
