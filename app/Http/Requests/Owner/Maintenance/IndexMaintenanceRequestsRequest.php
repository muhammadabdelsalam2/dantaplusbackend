<?php

namespace App\Http\Requests\Owner\Maintenance;

use App\Models\OwnerMaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMaintenanceRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(OwnerMaintenanceRequest::STATUSES)],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'company_id' => ['nullable', 'integer', 'exists:maintenance_companies,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
