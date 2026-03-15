<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaseMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_type' => ['required', 'string', 'max:50'],
            'message' => ['required_without:attachment', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
            'attachment' => ['required_without:message', 'nullable', 'file', 'max:10240'],
            'attachment_url' => ['nullable', 'string', 'max:2000'],
            'attachment_type' => ['nullable', 'string', 'max:100'],
        ];
    }
}
