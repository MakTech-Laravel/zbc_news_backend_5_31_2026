<?php

namespace App\Services\Newsletter;

use App\Models\NewsletterSubscriber;
use App\Models\SiteSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoContactService
{
    public function syncVerifiedSubscriber(NewsletterSubscriber $subscriber): void
    {
        $settings = SiteSettings::query()->first();
        $apiKey = $settings?->getAttribute('brevo_api_key');
        $listId = $settings?->getAttribute('brevo_list_id');

        if (! filled($apiKey) || (string) ($settings?->newsletter_provider ?? '') !== 'brevo') {
            return;
        }

        $payload = [
            'email' => $subscriber->email,
            'updateEnabled' => true,
            'attributes' => array_filter([
                'FIRSTNAME' => $subscriber->name,
            ]),
        ];

        if (filled($listId) && is_numeric($listId)) {
            $payload['listIds'] = [(int) $listId];
        }

        try {
            $response = Http::withHeaders([
                'api-key' => (string) $apiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/contacts', $payload);

            if ($response->status() === 204 || $response->successful()) {
                $contactId = $response->json('id');
                if ($contactId && $this->canStoreProviderContactId()) {
                    $subscriber->forceFill([
                        'provider_contact_id' => (string) $contactId,
                    ])->save();
                }

                return;
            }

            // Contact may already exist — try update by email.
            if ($response->status() === 400) {
                $this->updateExistingContact((string) $apiKey, $subscriber, $listId);

                return;
            }

            Log::warning('Brevo contact sync failed.', [
                'email' => $subscriber->email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Brevo contact sync exception.', [
                'email' => $subscriber->email,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function markUnsubscribed(NewsletterSubscriber $subscriber): void
    {
        $settings = SiteSettings::query()->first();
        $apiKey = $settings?->getAttribute('brevo_api_key');

        if (! filled($apiKey) || (string) ($settings?->newsletter_provider ?? '') !== 'brevo') {
            return;
        }

        try {
            $response = Http::withHeaders([
                'api-key' => (string) $apiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])->put('https://api.brevo.com/v3/contacts/'.urlencode($subscriber->email), [
                'emailBlacklisted' => true,
            ]);

            if (! $response->successful() && $response->status() !== 204) {
                Log::warning('Brevo unsubscribe sync failed.', [
                    'email' => $subscriber->email,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Brevo unsubscribe sync exception.', [
                'email' => $subscriber->email,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function updateExistingContact(string $apiKey, NewsletterSubscriber $subscriber, mixed $listId): void
    {
        $payload = [
            'attributes' => array_filter([
                'FIRSTNAME' => $subscriber->name,
            ]),
            'emailBlacklisted' => false,
        ];

        if (filled($listId) && is_numeric($listId)) {
            $payload['listIds'] = [(int) $listId];
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->put('https://api.brevo.com/v3/contacts/'.urlencode($subscriber->email), $payload);

        if (! $response->successful() && $response->status() !== 204) {
            Log::warning('Brevo contact update failed.', [
                'email' => $subscriber->email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function canStoreProviderContactId(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('newsletter_subscribers', 'provider_contact_id');
    }
}
