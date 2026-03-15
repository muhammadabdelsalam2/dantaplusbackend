<?php

namespace App\Http\Requests\Lab\Equipment;

use App\Models\LabEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLabEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $maintenanceStatus = $this->input('maintenance_status');

        if ($maintenanceStatus === 'All' || $maintenanceStatus === 'all') {
            $maintenanceStatus = null;
        }

        $this->merge([
            'maintenance_status' => $maintenanceStatus,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(LabEquipment::STATUSES)],
            'maintenance_status' => ['nullable', Rule::in(LabEquipment::MAINTENANCE_STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
