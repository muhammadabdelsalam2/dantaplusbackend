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
        ];
    }
}
