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
            'target_area' => ['nullable', 'string', 'max:100'],
            'procedure_id' => ['nullable', 'integer', 'exists:services,id'],
            'treating_doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:healthy,treated,inprogress,planned,problematic,Completed,InProgress'],
            'billing_method' => ['nullable', 'in:Cash,Insurance'],
            'clinical_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
