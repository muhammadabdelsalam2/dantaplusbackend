<?php

namespace App\Services\Clinic\WhatsappBot;

use App\Models\Clinic;
use App\Models\WhatsappBotSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AIReplyService
{
    public function generateReply(
        string $message,
        Clinic $clinic,
        WhatsappBotSetting $setting,
        ?string $intent = null,
        ?string $language = null
    ): string {
        $prompt = implode("\n", [
            'You are a dental clinic assistant. Help patients book, cancel, reschedule appointments, or answer questions.',
            'Keep replies concise, polite, and operational.',
            'Clinic name: ' . $clinic->name,
            'Preferred response language: ' . ($language ?: $setting->language),
            'Detected intent: ' . ($intent ?: 'INQUIRY'),
            'Allowed services: ' . implode(', ', $setting->allowed_services ?? []),
            'Require deposit: ' . ($setting->require_deposit ? 'yes' : 'no'),
            'Deposit amount: ' . ($setting->deposit_amount ?? 0),
            'Do not invent unavailable services or unsupported policies.',
        ]);

        return $this->requestText($prompt, $message, 220);
    }

    public function detectIntent(string $message): ?string
    {
        $prompt = implode("\n", [
            'Classify the patient message into exactly one intent.',
            'Allowed values only: BOOK_APPOINTMENT, CANCEL_APPOINTMENT, RESCHEDULE, INQUIRY, GREETING.',
            'Return only the intent label with no extra text.',
        ]);

        return $this->requestText($prompt, $message, 30);
    }

    private function requestText(string $instructions, string $input, int $maxOutputTokens): string
    {
        $apiKey = (string) config('services.openai.api_key');
        $baseUrl = rtrim((string) config('services.openai.base_url'), '/');
        $model = (string) config('services.openai.model');

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post($baseUrl . '/responses', [
                'model' => $model,
                'instructions' => $instructions,
                'input' => $input,
                'max_output_tokens' => $maxOutputTokens,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed with status ' . $response->status() . '.');
        }

        $text = $this->extractOutputText($response->json());

        if ($text === null || trim($text) === '') {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        return trim($text);
    }

    private function extractOutputText(array $payload): ?string
    {
        $topLevel = $payload['output_text'] ?? null;
        if (is_string($topLevel) && $topLevel !== '') {
            return $topLevel;
        }

        $chunks = [];

        foreach (($payload['output'] ?? []) as $outputItem) {
            foreach (($outputItem['content'] ?? []) as $contentItem) {
                $text = $contentItem['text'] ?? null;

                if (is_string($text) && $text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        return $chunks === [] ? null : implode("\n", $chunks);
    }
}
