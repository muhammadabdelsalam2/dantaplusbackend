<?php

namespace App\Http\Requests\Lab\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.case_id' => ['nullable', 'integer', 'exists:cases,id'],
            'items.*.lab_service_id' => ['nullable', 'integer', 'exists:lab_services,id'],
            'items.*.technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'items.*.service_name' => ['required', 'string', 'max:255'],
            'items.*.patient_name' => ['nullable', 'string', 'max:255'],
            'items.*.case_number' => ['nullable', 'string', 'max:255'],
            'items.*.teeth_numbers' => ['nullable', 'array'],
            'items.*.teeth_numbers.*' => ['integer', 'min:11', 'max:48'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.materials_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.materials' => ['nullable', 'array'],
            'items.*.materials.*.lab_material_id' => ['nullable', 'integer', 'exists:lab_materials,id'],
            'items.*.materials.*.material_name' => ['required_with:items.*.materials', 'string', 'max:255'],
            'items.*.materials.*.material_type' => ['nullable', 'string', 'max:255'],
            'items.*.materials.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.materials.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
