<?php

namespace App\Http\Requests\Lab\Equipment;

use App\Models\LabEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'model_serial_number' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['sometimes', 'date'],
            'last_maintenance_date' => ['sometimes', 'date'],
            'maintenance_cycle_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'status' => ['sometimes', Rule::in(LabEquipment::STATUSES)],
            'maintenance_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
