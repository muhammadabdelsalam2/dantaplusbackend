<?php

namespace App\Http\Requests\Owner\Communication;

use App\Models\CommunicationConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(CommunicationConversation::STATUSES)],
        ];
    }
}
