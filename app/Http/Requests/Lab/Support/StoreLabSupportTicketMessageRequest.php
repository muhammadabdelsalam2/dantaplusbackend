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
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
