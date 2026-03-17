<?php

namespace App\Enums;

enum WhatsAppProvider: string
{
    case MetaCloudApi = 'meta_cloud_api';
    case TwilioWhatsAppApi = 'twilio_whatsapp_api';
}
