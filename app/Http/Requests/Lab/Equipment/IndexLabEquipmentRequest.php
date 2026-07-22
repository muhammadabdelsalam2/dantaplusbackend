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
        } elseif (is_string($maintenanceStatus)) {
            $maintenanceStatus = match ($maintenanceStatus) {
                'Up to Date' => LabEquipment::MAINTENANCE_STATUS_KEY_UP_TO_DATE,
                'Due Soon' => LabEquipment::MAINTENANCE_STATUS_KEY_DUE_SOON,
                'Overdue' => LabEquipment::MAINTENANCE_STATUS_KEY_OVERDUE,
                'N/A' => LabEquipment::MAINTENANCE_STATUS_KEY_NA,
                default => $maintenanceStatus,
            };
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
            'maintenance_status' => ['nullable', Rule::in(LabEquipment::MAINTENANCE_STATUS_KEYS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
