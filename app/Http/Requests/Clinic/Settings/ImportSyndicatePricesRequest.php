<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ImportSyndicatePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2017', 'max:2100'],
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt'],
            'is_active_year' => ['nullable', 'boolean'],
        ];
    }
}
