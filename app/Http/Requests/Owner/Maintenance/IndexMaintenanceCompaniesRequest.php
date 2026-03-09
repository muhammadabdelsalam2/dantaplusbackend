<?php

namespace App\Http\Requests\Owner\Maintenance;

use App\Models\MaintenanceCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMaintenanceCompaniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([MaintenanceCompany::STATUS_ACTIVE, MaintenanceCompany::STATUS_INACTIVE])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
