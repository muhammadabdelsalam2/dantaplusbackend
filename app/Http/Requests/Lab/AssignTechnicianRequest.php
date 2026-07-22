<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class AssignTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_technician_id' => [
                'required_without:technician_id',
                'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('lab_id', auth()->user()?->lab_id);
                }),
            ],
            'technician_id' => [
                'required_without:assigned_technician_id',
                'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('lab_id', auth()->user()?->lab_id);
                }),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('assigned_technician_id') && $this->filled('technician_id')) {
            $this->merge(['assigned_technician_id' => $this->input('technician_id')]);
        }
    }
}
