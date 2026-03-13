<?php

namespace App\Http\Requests\Lab\DeliveryRep;

use App\Models\LabDeliveryRep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryRepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', LabDeliveryRep::STATUS_ACTIVE),
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'login_phone' => ['required', 'string', 'max:50', Rule::unique('users', 'phone')],
            'assigned_region_city' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'status' => ['required', Rule::in([
                LabDeliveryRep::STATUS_ACTIVE,
                LabDeliveryRep::STATUS_INACTIVE,
            ])],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
