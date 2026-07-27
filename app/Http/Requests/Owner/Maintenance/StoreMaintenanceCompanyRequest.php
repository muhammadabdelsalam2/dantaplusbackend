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
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('company_name')) {
            $data['name'] = $this->input('company_name');
        }

        if ($this->has('contact_person')) {
            $data['contact_person'] = $this->input('contact_person');
        }

        if ($this->has('status') && ! in_array($this->input('status'), [MaintenanceCompany::STATUS_ACTIVE, MaintenanceCompany::STATUS_INACTIVE], true)) {
            $data['status'] = $this->boolean('status') ? MaintenanceCompany::STATUS_ACTIVE : MaintenanceCompany::STATUS_INACTIVE;
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }
}
