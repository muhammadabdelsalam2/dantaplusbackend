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
            'equipment_id' => ['nullable', 'integer', 'exists:equipments,id'],
            'equipment_name' => ['nullable', 'string', 'max:255'],
            'malfunction_type' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'urgency' => ['required', Rule::in(['Low', 'Normal', 'High', 'Critical', 'low', 'normal', 'high', 'critical'])],
            'company_id' => ['nullable', 'integer', 'exists:maintenance_companies,id'],
            'attachment'=> ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4,mov,avi,webm,pdf,doc,docx', 'max:51200'],
        ];
    }
}
