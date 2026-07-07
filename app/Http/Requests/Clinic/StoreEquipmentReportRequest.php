<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'malfunction_type' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'urgency' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'company_id' => ['nullable', 'integer', 'exists:maintenance_companies,id'],
            'attachment'=> ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:10240'],
        ];
    }
}
