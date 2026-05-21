<?php

namespace App\Services\Clinic\WhatsappBot\Providers;

use App\Models\Clinic;

interface WhatsAppProviderInterface
{
    public function sendMessage(string $to, string $message, ?Clinic $clinic = null): array;
}
