<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsletterEvent;
use App\Models\NewsletterSubscriber;
use App\Models\SiteSettings;
use App\Services\Newsletter\NewsletterDeliveryStatusAdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class NewsletterWebhookController extends Controller
{
    public function __construct(
        private readonly NewsletterDeliveryStatusAdminNotifier $deliveryStatusAdminNotifier,
    ) {}

    public function brevo(Request $request)
    {
        $settings = SiteSettings::query()->first();
        $secret = (string) ($settings?->newsletter_webhook_secret ?? '');

        if ($secret !== '') {
            $provided = (string) (
                $request->header('X-Newsletter-Webhook-Secret')
                ?: $request->query('secret')
                ?: ''
            );

            if (! hash_equals($secret, $provided)) {
                return sendResponse(false, 'Unauthorized webhook.', null, HttpStatus::HTTP_UNAUTHORIZED);
            }
        }

        $events = $request->all();
        if ($this->isAssoc($events)) {
            $events = [$events];
        }

        if (! is_array($events)) {
            return sendResponse(true, 'No events.', null, HttpStatus::HTTP_OK);
        }

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $this->handleBrevoEvent($event);
        }

        return sendResponse(true, 'Webhook processed.', null, HttpStatus::HTTP_OK);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handleBrevoEvent(array $event): void
    {
        $email = strtolower(trim((string) ($event['email'] ?? '')));
        $eventName = strtolower(trim((string) ($event['event'] ?? $event['type'] ?? '')));

        if ($email === '' || $eventName === '') {
            return;
        }

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();
        if (! $subscriber) {
            return;
        }

        $mappedType = match (true) {
            in_array($eventName, ['hard_bounce', 'soft_bounce', 'bounce'], true) => 'bounce',
            in_array($eventName, ['spam', 'complaint'], true) => 'complaint',
            in_array($eventName, ['unsubscribed', 'unsubscribe'], true) => 'unsubscribe',
            in_array($eventName, ['delivered', 'sent', 'request'], true) => 'sent',
            in_array($eventName, ['opened', 'open', 'unique_opened'], true) => 'open',
            in_array($eventName, ['click', 'clicks'], true) => 'click',
            in_array($eventName, ['error', 'blocked', 'invalid'], true) => 'failed',
            default => null,
        };

        if ($mappedType === null) {
            Log::info('Unhandled Brevo newsletter webhook event.', [
                'event' => $eventName,
                'email' => $email,
            ]);

            return;
        }

        $wasUnsubscribed = $subscriber->status === 'unsubscribed';

        $newsletterEvent = NewsletterEvent::query()->create([
            'newsletter_subscriber_id' => $subscriber->id,
            'event_type' => $mappedType,
            'meta' => [
                'provider' => 'brevo',
                'raw_event' => $eventName,
                'payload' => $event,
            ],
        ]);

        if (in_array($mappedType, ['bounce', 'complaint', 'unsubscribe'], true)) {
            $subscriber->update([
                'status' => 'unsubscribed',
                'unsubscribed_at' => $subscriber->unsubscribed_at ?? now(),
            ]);
        }

        $adminStatus = match ($mappedType) {
            'sent' => 'delivered',
            'failed' => 'failed',
            'bounce' => 'bounced',
            'unsubscribe' => 'unsubscribed',
            default => null,
        };

        if ($adminStatus === null) {
            return;
        }

        if ($adminStatus === 'unsubscribed' && $wasUnsubscribed) {
            return;
        }

        $this->deliveryStatusAdminNotifier->notify(
            $subscriber->fresh() ?? $subscriber,
            $adminStatus,
            $newsletterEvent->id,
            $eventName,
        );
    }

    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
