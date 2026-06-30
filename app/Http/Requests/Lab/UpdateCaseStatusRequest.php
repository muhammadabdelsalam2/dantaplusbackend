<?php

namespace App\Http\Requests\Lab;

use App\Models\CaseModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(CaseModel::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'assigned_technician_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('lab_id', auth()->user()?->lab_id);
                }),
            ],
            'delivery_rep_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'generate_invoice' => ['nullable', 'boolean'],
            'assign_for_delivery' => ['nullable', 'boolean'],
            'scheduled_for' => ['nullable', 'date'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'pickup_notes' => ['nullable', 'string', 'max:1000'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
