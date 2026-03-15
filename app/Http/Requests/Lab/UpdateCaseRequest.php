<?php

namespace App\Http\Requests\Lab;

use App\Models\CaseModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'due_date' => ['sometimes', 'date'],
            'case_type' => ['sometimes', 'string', 'max:255'],
            'tooth_numbers' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::in(CaseModel::PRIORITIES)],
            'assigned_delivery_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
