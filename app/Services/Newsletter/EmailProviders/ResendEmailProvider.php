<?php

namespace App\Services\Newsletter\EmailProviders;

use App\Contracts\Newsletter\EmailProviderInterface;
use Illuminate\Support\Facades\Http;

class ResendEmailProvider implements EmailProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function send(array $payload): void
    {
        $response = Http::withToken($this->apiKey)
            ->post('https://api.resend.com/emails', [
                'from' => "{$payload['from_name']} <{$payload['from_email']}>",
                'to' => [$payload['to']],
                'subject' => $payload['subject'],
                'html' => $payload['html'],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Resend API error: ' . $response->body());
        }
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }
}
