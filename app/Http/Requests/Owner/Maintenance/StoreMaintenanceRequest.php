<?php

namespace App\Http\Requests\Owner\Maintenance;

use App\Models\OwnerMaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'equipment' => ['required', 'string', 'max:255'],
            'issue_description' => ['required', 'string', 'max:2000'],
            'assigned_company_id' => ['nullable', 'integer', 'exists:maintenance_companies,id'],
            'status' => ['nullable', Rule::in(OwnerMaintenanceRequest::STATUSES)],
        ];
    }
}
