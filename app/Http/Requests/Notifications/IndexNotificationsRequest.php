<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->input('user_id', $this->input('userId')),
            'date_from' => $this->input('date_from', $this->input('dateFrom')),
            'date_to' => $this->input('date_to', $this->input('dateTo')),
            'per_page' => $this->input('per_page', $this->input('perPage')),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['system', 'appointment', 'payment', 'custom', 'reminder', 'announcement'])],
            'priority' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', Rule::in(['super_admin', 'owner', 'clinic'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['read', 'unread'])],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
