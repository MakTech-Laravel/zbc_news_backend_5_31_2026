<?php

namespace App\Services\Newsletter\EmailProviders;

use App\Contracts\Newsletter\EmailProviderInterface;
use Illuminate\Support\Facades\Http;

class MailchimpEmailProvider implements EmailProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function send(array $payload): void
    {
        $dc = $this->dataCenter();
        $response = Http::withBasicAuth('anystring', $this->apiKey)
            ->post("https://{$dc}.api.mailchimp.com/3.0/transactional/messages", [
                'message' => [
                    'from_email' => $payload['from_email'],
                    'from_name' => $payload['from_name'],
                    'subject' => $payload['subject'],
                    'html' => $payload['html'],
                    'to' => [[
                        'email' => $payload['to'],
                        'name' => $payload['to_name'] ?? '',
                        'type' => 'to',
                    ]],
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Mailchimp API error: ' . $response->body());
        }
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    private function dataCenter(): string
    {
        $parts = explode('-', $this->apiKey);

        return end($parts) ?: 'us1';
    }
}
