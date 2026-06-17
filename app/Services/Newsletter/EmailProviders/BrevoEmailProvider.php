<?php

namespace App\Services\Newsletter\EmailProviders;

use App\Contracts\Newsletter\EmailProviderInterface;
use Illuminate\Support\Facades\Http;

class BrevoEmailProvider implements EmailProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function send(array $payload): void
    {
        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender' => [
                'name' => $payload['from_name'],
                'email' => $payload['from_email'],
            ],
            'to' => [[
                'email' => $payload['to'],
                'name' => $payload['to_name'] ?? null,
            ]],
            'subject' => $payload['subject'],
            'htmlContent' => $payload['html'],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Brevo API error: ' . $response->body());
        }
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }
}
