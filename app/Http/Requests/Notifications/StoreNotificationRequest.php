<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $deliveryMethod = $this->input('delivery_method', $this->input('delivery_methods', $this->input('deliveryMethod')));

        $this->merge([
            'user_id' => $this->input('user_id', $this->input('userId')),
            'sender_id' => $this->input('sender_id', $this->input('senderId')),
            'sender_name' => $this->input('sender_name', $this->input('senderName')),
            'delivery_method' => is_string($deliveryMethod) ? [$deliveryMethod] : $deliveryMethod,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['required', Rule::in(['system', 'appointment', 'payment', 'custom'])],
            'priority' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', Rule::in(['super_admin', 'owner', 'clinic'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'delivery_method' => ['nullable', 'array'],
            'delivery_method.*' => ['string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'sender_id' => ['nullable', 'integer', 'exists:users,id'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:255'],
        ];
    }
}
