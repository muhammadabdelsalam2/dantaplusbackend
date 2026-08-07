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

    protected function prepareForValidation(): void
    {
        if (! $this->has('assigned_company_id') && $this->has('assigned_company')) {
            $this->merge(['assigned_company_id' => $this->input('assigned_company')]);
        }
    }
}
