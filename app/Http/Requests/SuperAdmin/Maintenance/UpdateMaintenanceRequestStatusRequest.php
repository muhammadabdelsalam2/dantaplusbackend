<?php

namespace App\Http\Requests\SuperAdmin\Maintenance;

use App\Models\OwnerMaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(OwnerMaintenanceRequest::STATUSES)],
            'assigned_company_id' => ['nullable', 'integer', 'exists:maintenance_companies,id'],
        ];
    }
}
