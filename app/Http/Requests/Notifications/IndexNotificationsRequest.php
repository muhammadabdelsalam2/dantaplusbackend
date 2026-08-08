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
            'tab' => $this->normalizeTab($this->input('tab', 'all')),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', Rule::in(['all', 'warning', 'reached', 'branch_limit', 'user_limit', 'usage_limit'])],
            'type' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', Rule::in(['super_admin', 'owner', 'clinic', 'patient'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['read', 'unread'])],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function normalizeTab(?string $tab): string
    {
        return strtolower(str_replace(' ', '_', (string) $tab));
    }
}
