<?php

namespace App\Services\Newsletter;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterEvent;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\URL;

class NewsletterTrackingService
{
    public function sign(int $campaignId, int $subscriberId): string
    {
        return hash_hmac('sha256', "{$campaignId}:{$subscriberId}", (string) config('app.key'));
    }

    public function verify(int $campaignId, int $subscriberId, string $signature): bool
    {
        return hash_equals($this->sign($campaignId, $subscriberId), $signature);
    }

    public function openPixelUrl(int $campaignId, int $subscriberId): string
    {
        $signature = $this->sign($campaignId, $subscriberId);

        return URL::to("/api/v1/newsletter/track/open/{$campaignId}/{$subscriberId}/{$signature}");
    }

    public function clickUrl(int $campaignId, int $subscriberId, string $targetUrl): string
    {
        $signature = $this->sign($campaignId, $subscriberId);

        return URL::to('/api/v1/newsletter/track/click/' . $campaignId . '/' . $subscriberId . '/' . $signature)
            . '?url=' . urlencode($targetUrl);
    }

    public function wrapHtmlLinks(string $html, int $campaignId, int $subscriberId): string
    {
        return (string) preg_replace_callback(
            '/href=(["\'])(https?:\/\/[^"\']+)\1/i',
            function (array $matches) use ($campaignId, $subscriberId): string {
                $tracked = $this->clickUrl($campaignId, $subscriberId, $matches[2]);

                return 'href=' . $matches[1] . $tracked . $matches[1];
            },
            $html,
        );
    }

    public function injectTrackingPixel(string $html, int $campaignId, int $subscriberId): string
    {
        $pixel = '<img src="' . e($this->openPixelUrl($campaignId, $subscriberId)) . '" width="1" height="1" alt="" style="display:none" />';

        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $pixel . '</body>', $html);
        }

        return $html . $pixel;
    }

    public function appendUnsubscribeFooter(string $html, NewsletterSubscriber $subscriber): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $unsubscribeUrl = $frontendUrl . '/newsletter/unsubscribe?token=' . $subscriber->unsubscribe_token;
        $preferencesUrl = $frontendUrl . '/newsletter/preferences?token=' . $subscriber->unsubscribe_token;

        $footer = '<hr style="margin-top:32px;border:none;border-top:1px solid #e5e7eb" />'
            . '<p style="font-size:12px;color:#6b7280">'
            . '<a href="' . e($preferencesUrl) . '">Manage preferences</a> · '
            . '<a href="' . e($unsubscribeUrl) . '">Unsubscribe</a>'
            . '</p>';

        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $footer . '</body>', $html);
        }

        return $html . $footer;
    }

    public function recordOpen(NewsletterCampaign $campaign, NewsletterSubscriber $subscriber, ?array $meta = null): void
    {
        $alreadyOpened = NewsletterEvent::query()
            ->where('newsletter_campaign_id', $campaign->id)
            ->where('newsletter_subscriber_id', $subscriber->id)
            ->where('event_type', 'open')
            ->exists();

        NewsletterEvent::query()->create([
            'newsletter_campaign_id' => $campaign->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'event_type' => 'open',
            'meta' => $meta,
        ]);

        if (!$alreadyOpened) {
            $campaign->increment('open_count');
        }
    }

    public function recordClick(NewsletterCampaign $campaign, NewsletterSubscriber $subscriber, string $url, ?array $meta = null): void
    {
        NewsletterEvent::query()->create([
            'newsletter_campaign_id' => $campaign->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'event_type' => 'click',
            'meta' => array_merge($meta ?? [], ['url' => $url]),
        ]);

        $campaign->increment('click_count');
    }

    public function recordSent(NewsletterCampaign $campaign, NewsletterSubscriber $subscriber, ?array $meta = null): void
    {
        NewsletterEvent::query()->create([
            'newsletter_campaign_id' => $campaign->id,
            'newsletter_subscriber_id' => $subscriber->id,
            'event_type' => 'sent',
            'meta' => $meta,
        ]);
    }
}
