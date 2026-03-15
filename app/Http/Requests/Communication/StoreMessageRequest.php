<?php

namespace App\Http\Requests\Communication;

use App\Models\CommunicationMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_type' => ['nullable', 'string', 'max:50'],
            'text' => ['required_without:attachment_url', 'string', 'max:5000'],
            'type' => ['sometimes', Rule::in(CommunicationMessage::TYPES)],
            'related_id' => ['nullable', 'string', 'max:100'],
            'attachment_url' => ['nullable', 'string', 'max:2000'],
            'is_system_message' => ['sometimes', 'boolean'],
        ];
    }
}
