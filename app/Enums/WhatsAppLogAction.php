<?php

namespace App\Enums;

enum WhatsAppLogAction: string
{
    case TestSent = 'Test Sent';
    case SettingsUpdated = 'Settings Updated';
    case ConnectionFailed = 'Connection Failed';
    case WebhookReceived = 'Webhook Received';
}
