<?php

namespace App\Http\Requests\Lab\Equipment;

use App\Models\LabEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', LabEquipment::STATUS_OPERATIONAL),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'model_serial_number' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'last_maintenance_date' => ['required', 'date'],
            'maintenance_cycle_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'status' => ['required', Rule::in(LabEquipment::STATUSES)],
            'maintenance_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
