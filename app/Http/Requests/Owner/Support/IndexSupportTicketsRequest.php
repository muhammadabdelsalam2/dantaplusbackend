<?php

namespace App\Http\Requests\Owner\Support;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSupportTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['nullable', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'reporter_type' => ['nullable', Rule::in(SupportTicket::REPORTER_TYPES)],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'lab_id' => ['nullable', 'integer', 'exists:dental_labs,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
