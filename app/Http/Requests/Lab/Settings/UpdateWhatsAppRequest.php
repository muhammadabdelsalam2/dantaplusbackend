<?php

namespace App\Http\Requests\Lab\Settings;

use App\Enums\WhatsAppProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['nullable'],
            'business_phone_number_id' => ['nullable', 'string'],
            'whatsapp_business_account_id' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'verify_token' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
            'meta.whatsapp_business_account_id' => ['nullable', 'string'],
            'meta.business_phone_number_id' => ['nullable', 'string'],
            'meta.access_token' => ['nullable', 'string'],
            'meta.verify_token' => ['nullable', 'string'],
            'twilio' => ['nullable', 'array'],
            'twilio.account_sid' => ['nullable', 'string'],
            'twilio.auth_token' => ['nullable', 'string'],
            'twilio.phone_number' => ['nullable', 'string'],
        ];
    }
}
