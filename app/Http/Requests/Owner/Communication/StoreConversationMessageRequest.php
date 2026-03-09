<?php

namespace App\Http\Requests\Owner\Communication;

use App\Models\CommunicationMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_type' => ['nullable', Rule::in(['super-admin', 'clinic', 'lab', 'system'])],
            'text' => ['nullable', 'string', 'max:5000', 'required_without:attachment_url'],
            'type' => ['nullable', Rule::in(CommunicationMessage::TYPES)],
            'related_id' => ['nullable', 'string', 'max:255'],
            'attachment_url' => ['nullable', 'string', 'max:2048', 'required_without:text'],
            'is_system_message' => ['nullable', 'boolean'],
        ];
    }
}
