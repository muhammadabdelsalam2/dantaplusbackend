<?php

namespace App\Http\Requests\Owner\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'audience_type' => ['nullable', 'string', 'max:50'],
            'audience_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'string', 'max:50'],
            'delivery_methods' => ['nullable', 'array'],
            'delivery_methods.*' => ['string', 'max:50'],
            'sender_id' => ['nullable', 'integer', 'exists:users,id'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:255'],
        ];
    }
}
