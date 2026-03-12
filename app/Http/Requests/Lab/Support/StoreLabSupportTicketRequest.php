<?php

namespace App\Http\Requests\Lab\Support;

use App\Models\LabSupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('priority')) {
            $this->merge([
                'priority' => LabSupportTicket::PRIORITY_MEDIUM,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'priority' => ['required', Rule::in([
                LabSupportTicket::PRIORITY_LOW,
                LabSupportTicket::PRIORITY_MEDIUM,
                LabSupportTicket::PRIORITY_HIGH,
            ])],
            'description' => ['required', 'string', 'max:5000'],
            'attachment' => ['sometimes', 'nullable', 'file', 'max:10240'],
        ];
    }
}
