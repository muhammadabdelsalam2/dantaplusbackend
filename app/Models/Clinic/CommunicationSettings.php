<?php

namespace App\Models\Clinic;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationSettings extends Model
{
    protected $table = 'clinic_communication_settings';

    protected $fillable = [
        'clinic_id',
        'whatsapp_provider',
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'whatsapp_access_token',
        'whatsapp_app_id',
        'whatsapp_app_secret',
        'whatsapp_webhook_verify_token',
        'sms_api_key',
        'sms_sender_name',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_name',
        'smtp_from_email',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_access_token' => 'encrypted',
            'whatsapp_app_secret' => 'encrypted',
            'whatsapp_webhook_verify_token' => 'encrypted',
            'sms_api_key' => 'encrypted',
            'smtp_password' => 'encrypted',
            'smtp_port' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
