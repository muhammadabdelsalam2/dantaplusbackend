<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreDentalChartEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tooth_number' => ['required_without:tooth_numbers', 'string', 'max:20'],
            'tooth_numbers' => ['required_without:tooth_number', 'array', 'min:1'],
            'tooth_numbers.*' => ['string', 'max:20', 'distinct'],
            'status' => ['nullable', 'in:healthy,treated,inprogress,planned,problematic'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
