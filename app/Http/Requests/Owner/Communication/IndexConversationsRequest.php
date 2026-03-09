<?php

namespace App\Http\Requests\Owner\Communication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['open', 'closed', 'unread'])],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'lab_id' => ['nullable', 'integer', 'exists:dental_labs,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
