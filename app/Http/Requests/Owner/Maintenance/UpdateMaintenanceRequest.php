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
            'assigned_company_id' => ['sometimes', 'nullable', 'integer', 'exists:maintenance_companies,id'],
            'status' => ['sometimes', Rule::in(OwnerMaintenanceRequestModel::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('assigned_company_id') && $this->has('assigned_company')) {
            $this->merge(['assigned_company_id' => $this->input('assigned_company')]);
        }

        if ($this->input('status') === 'Resolved') {
            $this->merge(['status' => OwnerMaintenanceRequestModel::STATUS_COMPLETED]);
        }
    }
}
