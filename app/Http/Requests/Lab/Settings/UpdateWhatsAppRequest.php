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
            'provider' => ['required', Rule::enum(WhatsAppProvider::class)],
            'meta' => ['required_if:provider,' . WhatsAppProvider::MetaCloudApi->value, 'array'],
            'meta.whatsapp_business_account_id' => ['required_if:provider,' . WhatsAppProvider::MetaCloudApi->value, 'string'],
            'meta.business_phone_number_id' => ['required_if:provider,' . WhatsAppProvider::MetaCloudApi->value, 'string'],
            'meta.access_token' => ['required_if:provider,' . WhatsAppProvider::MetaCloudApi->value, 'string'],
            'meta.verify_token' => ['required_if:provider,' . WhatsAppProvider::MetaCloudApi->value, 'string'],
            'twilio' => ['required_if:provider,' . WhatsAppProvider::TwilioWhatsAppApi->value, 'array'],
            'twilio.account_sid' => ['required_if:provider,' . WhatsAppProvider::TwilioWhatsAppApi->value, 'string'],
            'twilio.auth_token' => ['required_if:provider,' . WhatsAppProvider::TwilioWhatsAppApi->value, 'string'],
            'twilio.phone_number' => ['required_if:provider,' . WhatsAppProvider::TwilioWhatsAppApi->value, 'string'],
        ];
    }
}
