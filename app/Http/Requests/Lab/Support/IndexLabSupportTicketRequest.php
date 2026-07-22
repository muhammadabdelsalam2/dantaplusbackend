<?php

namespace App\Http\Requests\Lab\Support;

use App\Models\LabSupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLabSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status') && is_string($this->input('status'))) {
            $status = strtolower(str_replace(' ', '_', $this->input('status')));
            $this->merge(['status' => in_array($status, ['all', 'all_statuses', ''], true) ? null : $status]);
        }

        if ($this->has('priority') && is_string($this->input('priority'))) {
            $priority = strtolower($this->input('priority'));
            $this->merge(['priority' => in_array($priority, ['all', 'all_priorities', ''], true) ? null : $priority]);
        }

        if ($this->has('per_page')) {
            $this->merge([
                'per_page' => (int) $this->input('per_page'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in([
                LabSupportTicket::STATUS_OPEN,
                LabSupportTicket::STATUS_IN_PROGRESS,
                LabSupportTicket::STATUS_RESOLVED,
            ])],
            'priority' => ['sometimes', 'nullable', Rule::in([
                LabSupportTicket::PRIORITY_LOW,
                LabSupportTicket::PRIORITY_MEDIUM,
                LabSupportTicket::PRIORITY_HIGH,
            ])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
