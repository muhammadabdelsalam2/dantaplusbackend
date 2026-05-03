<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAllNotificationsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_from' => $this->input('date_from', $this->input('dateFrom')),
            'date_to' => $this->input('date_to', $this->input('dateTo')),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['system', 'appointment', 'payment', 'custom', 'reminder', 'announcement'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
