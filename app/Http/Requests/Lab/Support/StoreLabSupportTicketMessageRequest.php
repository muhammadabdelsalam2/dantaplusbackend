<?php

namespace App\Http\Requests\Lab\Support;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabSupportTicketMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'attachment' => ['sometimes', 'nullable', 'file', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $message = trim((string) $this->input('message', ''));
            if ($message === '' && ! $this->hasFile('attachment')) {
                $validator->errors()->add('message', 'Please provide a message, an attachment, or both.');
            }
        });
    }
}
