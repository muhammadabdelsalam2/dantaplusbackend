<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'message_type' => 'required|in:text,image,file,invoice',
            'content' => 'nullable|string',
            'related_type' => 'nullable|string|max:50',
            'related_id' => 'nullable|integer',
            'attachment' => 'nullable|file|max:5120',
        ];
    }
}
