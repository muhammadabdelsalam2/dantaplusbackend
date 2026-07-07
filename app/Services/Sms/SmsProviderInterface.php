<?php

namespace App\Services\Sms;

interface SmsProviderInterface
{
    public function sendMessage(string $to, string $message, array $context = []): array;
}

