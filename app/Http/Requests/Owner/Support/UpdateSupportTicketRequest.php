<?php

namespace App\Http\Requests\Owner\Support;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketRequest extends FormRequest
{
    private const PRIORITY_VALUES = ['High', 'Medium', 'Low', 'high', 'medium', 'low'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['nullable', Rule::in(self::PRIORITY_VALUES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'category' => ['nullable', 'string', 'max:255'],
        ];
    }
 
}
