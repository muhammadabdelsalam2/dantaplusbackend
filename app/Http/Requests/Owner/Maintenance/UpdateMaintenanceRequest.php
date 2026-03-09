<?php

namespace App\Http\Requests\Owner\Maintenance;

use App\Models\OwnerMaintenanceRequest as OwnerMaintenanceRequestModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment' => ['sometimes', 'string', 'max:255'],
            'issue_description' => ['sometimes', 'string', 'max:2000'],
            'assigned_company_id' => ['sometimes', 'nullable', 'integer', 'exists:maintenance_companies,id'],
            'status' => ['sometimes', Rule::in(OwnerMaintenanceRequestModel::STATUSES)],
        ];
    }
}
