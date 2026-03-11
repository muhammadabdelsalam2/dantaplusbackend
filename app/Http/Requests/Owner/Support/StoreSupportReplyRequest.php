<?php

namespace App\Http\Requests\Owner\Support;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
            'sender_id' => ['nullable', 'integer', 'exists:users,id'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_role' => ['nullable', 'string', 'max:50'],
        ];
    }
}
