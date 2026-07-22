<?php

namespace App\Http\Requests\Lab\Equipment;

use App\Models\LabEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordLabEquipmentMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_date' => ['nullable', 'date'],
            'next_due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'maintenance_notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(LabEquipment::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('maintenance_notes') && $this->filled('notes')) {
            $this->merge(['maintenance_notes' => $this->input('notes')]);
        }
    }
}
