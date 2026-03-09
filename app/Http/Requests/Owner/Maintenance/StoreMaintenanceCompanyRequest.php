<?php

namespace App\Http\Requests\Owner\Maintenance;

use App\Models\MaintenanceCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', Rule::in([MaintenanceCompany::STATUS_ACTIVE, MaintenanceCompany::STATUS_INACTIVE])],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'ai_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'feedback' => ['nullable', 'array'],
            'reports' => ['nullable', 'array'],
        ];
    }
}
