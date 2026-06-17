<?php

namespace App\Services\Newsletter;

use App\Jobs\SendNewsletterCampaignJob;
use App\Mail\NewsletterVerificationMail;
use App\Models\ArticleCategory;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterEvent;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NewsletterService
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    public function __construct(
        private readonly NewsletterEmailProviderFactory $providerFactory,
        private readonly NewsletterTrackingService $trackingService,
    ) {}

    public function subscribe(array $data, ?User $user = null): NewsletterSubscriber
    {
        $email = strtolower(trim((string) $data['email']));
        $verificationToken = Str::random(64);
        $unsubscribeToken = Str::random(64);

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $email],
            $this->subscriberPayload($data, $user, $verificationToken, $unsubscribeToken),
        );

        $this->sendVerificationEmail($subscriber);

        return $subscriber;
    }

    public function sendVerificationEmail(NewsletterSubscriber $subscriber): void
    {
        $verifyUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/')
            . '/newsletter/verify?token=' . $subscriber->verification_token;

        $from = $this->providerFactory->fromAddress();
        $siteName = $from['name'] ?: 'ZBC News';
        $html = "<p>Thanks for subscribing to {$siteName}.</p>"
            . "<p>Please verify your email by clicking the link below:</p>"
            . "<p><a href=\"{$verifyUrl}\">Verify subscription</a></p>";

        try {
            $this->providerFactory->make()->send([
                'to' => $subscriber->email,
                'to_name' => $subscriber->name,
                'subject' => "Verify your {$siteName} newsletter subscription",
                'html' => $html,
                'from_email' => $from['email'],
                'from_name' => $from['name'],
            ]);
        } catch (\Throwable) {
            // Keep subscription even if mail transport is unavailable locally.
        }
    }

    public function verify(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('verification_token', trim($token))
            ->first();

        if (!$subscriber) {
            return null;
        }

        $subscriber->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verification_token' => null,
        ]);

        return $subscriber;
    }

    public function unsubscribe(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('unsubscribe_token', trim($token))
            ->first();

        if (!$subscriber) {
            return null;
        }

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        NewsletterEvent::query()->create([
            'newsletter_subscriber_id' => $subscriber->id,
            'event_type' => 'unsubscribe',
            'meta' => ['source' => 'link'],
        ]);

        return $subscriber;
    }

    public function getPreferencesByToken(string $token): ?NewsletterSubscriber
    {
        return NewsletterSubscriber::query()
            ->where('unsubscribe_token', trim($token))
            ->where('status', '!=', 'unsubscribed')
            ->first();
    }

    public function updatePreferences(string $token, array $preferences): ?NewsletterSubscriber
    {
        $subscriber = $this->getPreferencesByToken($token);

        if (!$subscriber) {
            return null;
        }

        $subscriber->update([
            'preferences' => $this->normalizePreferences($preferences),
        ]);

        return $subscriber->fresh();
    }

    public function listSubscribers(?string $status = null): LengthAwarePaginator
    {
        $query = NewsletterSubscriber::query()->latest('id');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return $query->paginate(20);
    }

    public function deleteSubscriber(int $id): bool
    {
        return (bool) NewsletterSubscriber::query()->whereKey($id)->delete();
    }

    public function updateSubscriberStatus(int $id, string $status): ?NewsletterSubscriber
    {
        $allowed = ['pending', 'verified', 'unsubscribed'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid subscriber status.');
        }

        $subscriber = NewsletterSubscriber::query()->find($id);
        if (!$subscriber) {
            return null;
        }

        $updates = ['status' => $status];

        if ($status === 'verified') {
            $updates['verified_at'] = now();
            $updates['verification_token'] = null;
            $updates['unsubscribed_at'] = null;
        } elseif ($status === 'pending') {
            $updates['verified_at'] = null;
            $updates['unsubscribed_at'] = null;
            if (empty($subscriber->verification_token)) {
                $updates['verification_token'] = Str::random(64);
            }
        } else {
            $updates['unsubscribed_at'] = now();
        }

        $subscriber->update($updates);

        if ($status === 'unsubscribed') {
            NewsletterEvent::query()->create([
                'newsletter_subscriber_id' => $subscriber->id,
                'event_type' => 'unsubscribe',
                'meta' => ['source' => 'admin'],
            ]);
        }

        return $subscriber->fresh();
    }

    public function resendSubscriberVerification(int $id): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()->find($id);
        if (!$subscriber || $subscriber->status !== 'pending') {
            return null;
        }

        if (empty($subscriber->verification_token)) {
            $subscriber->update(['verification_token' => Str::random(64)]);
            $subscriber = $subscriber->fresh();
        }

        $this->sendVerificationEmail($subscriber);

        return $subscriber;
    }

    public function listCampaigns(): LengthAwarePaginator
    {
        return NewsletterCampaign::query()->latest('id')->paginate(20);
    }

    public function getCampaign(int $id): ?NewsletterCampaign
    {
        return NewsletterCampaign::query()->find($id);
    }

    public function createCampaign(array $data): NewsletterCampaign
    {
        return NewsletterCampaign::query()->create($this->campaignPayload($data));
    }

    public function updateCampaign(NewsletterCampaign $campaign, array $data): NewsletterCampaign
    {
        if (in_array($campaign->status, ['sent', 'sending'], true)) {
            throw new \RuntimeException('Sent campaigns cannot be edited.');
        }

        $campaign->update($this->campaignPayload($data));

        return $campaign->fresh();
    }

    public function scheduleCampaign(NewsletterCampaign $campaign, Carbon $scheduledAt): NewsletterCampaign
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw new \RuntimeException('Only draft campaigns can be scheduled.');
        }

        $campaign->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);

        return $campaign->fresh();
    }

    public function dispatchCampaign(NewsletterCampaign $campaign): NewsletterCampaign
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw new \RuntimeException('Campaign cannot be sent in its current state.');
        }

        $recipients = $this->recipientsQuery($campaign)->count();

        if ($recipients === 0) {
            throw new \RuntimeException('No verified subscribers match this campaign audience.');
        }

        $campaign->update([
            'status' => 'sending',
            'scheduled_at' => $campaign->scheduled_at ?? now(),
            'subscriber_count' => $recipients,
            'failed_count' => 0,
        ]);

        SendNewsletterCampaignJob::dispatch($campaign->id);

        return $campaign->fresh();
    }

    public function processDueScheduledCampaigns(): int
    {
        $count = 0;

        NewsletterCampaign::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->each(function (NewsletterCampaign $campaign) use (&$count): void {
                $this->dispatchCampaign($campaign);
                $count++;
            });

        return $count;
    }

    public function recipientsQuery(NewsletterCampaign $campaign): Builder
    {
        $query = NewsletterSubscriber::query()
            ->where('status', 'verified');

        if ($campaign->premium_only) {
            $query->where('is_premium', true);
        }

        $segments = is_array($campaign->segments) ? $campaign->segments : [];
        $categorySlugs = $segments['category_slugs'] ?? $segments['categories'] ?? null;

        if (is_array($categorySlugs) && count($categorySlugs) > 0) {
            $query->where(function (Builder $inner) use ($categorySlugs): void {
                foreach ($categorySlugs as $slug) {
                    $inner->orWhereJsonContains('preferences->categories', $slug);
                }
            });
        }

        $audienceTags = $segments['audience_tags'] ?? null;
        if (is_array($audienceTags) && count($audienceTags) > 0) {
            foreach ($audienceTags as $tag) {
                $query->whereJsonContains('audience_tags', $tag);
            }
        }

        return $query;
    }

    public function buildEmailHtml(NewsletterCampaign $campaign, NewsletterSubscriber $subscriber): string
    {
        $html = $this->trackingService->wrapHtmlLinks($campaign->content_html, $campaign->id, $subscriber->id);
        $html = $this->trackingService->injectTrackingPixel($html, $campaign->id, $subscriber->id);
        $html = $this->trackingService->appendUnsubscribeFooter($html, $subscriber);

        return $html;
    }

    public function sendCampaignEmail(NewsletterCampaign $campaign, NewsletterSubscriber $subscriber): void
    {
        $from = $this->providerFactory->fromAddress();
        $provider = $this->providerFactory->make();

        $provider->send([
            'to' => $subscriber->email,
            'to_name' => $subscriber->name,
            'subject' => $campaign->subject,
            'html' => $this->buildEmailHtml($campaign, $subscriber),
            'from_email' => $from['email'],
            'from_name' => $from['name'],
        ]);

        $this->trackingService->recordSent($campaign, $subscriber);
    }

    public function markCampaignSent(NewsletterCampaign $campaign): void
    {
        $campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function incrementFailed(NewsletterCampaign $campaign): void
    {
        if (!$this->hasColumn('newsletter_campaigns', 'failed_count')) {
            return;
        }

        $campaign->increment('failed_count');
    }

  /**
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        $verified = NewsletterSubscriber::query()->where('status', 'verified')->count();
        $pending = NewsletterSubscriber::query()->where('status', 'pending')->count();
        $unsubscribed = NewsletterSubscriber::query()->where('status', 'unsubscribed')->count();

        $growth = NewsletterSubscriber::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => ['date' => $row->day, 'count' => (int) $row->total])
            ->values()
            ->all();

        $campaignColumns = ['id', 'title', 'sent_at', 'subscriber_count', 'open_count', 'click_count'];
        if ($this->hasColumn('newsletter_campaigns', 'failed_count')) {
            $campaignColumns[] = 'failed_count';
        }

        $campaignStats = NewsletterCampaign::query()
            ->where('status', 'sent')
            ->latest('sent_at')
            ->limit(10)
            ->get($campaignColumns)
            ->map(function (NewsletterCampaign $campaign) {
                $sent = max(1, (int) $campaign->subscriber_count);

                return [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'sent_at' => $campaign->sent_at,
                    'subscriber_count' => $campaign->subscriber_count,
                    'open_count' => $campaign->open_count,
                    'click_count' => $campaign->click_count,
                    'failed_count' => (int) ($campaign->failed_count ?? 0),
                    'open_rate' => round(((int) $campaign->open_count / $sent) * 100, 1),
                    'click_rate' => round(((int) $campaign->click_count / $sent) * 100, 1),
                ];
            })
            ->values()
            ->all();

        $recentEvents = NewsletterEvent::query()
            ->with(['campaign:id,title', 'subscriber:id,email'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (NewsletterEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'campaign' => $event->campaign?->title,
                'email' => $event->subscriber?->email,
                'meta' => $event->meta,
                'created_at' => $event->created_at,
            ])
            ->values()
            ->all();

        $totals = NewsletterCampaign::query()
            ->where('status', 'sent')
            ->selectRaw('COALESCE(SUM(subscriber_count),0) as sent, COALESCE(SUM(open_count),0) as opens, COALESCE(SUM(click_count),0) as clicks')
            ->first();

        return [
            'subscribers' => [
                'verified' => $verified,
                'pending' => $pending,
                'unsubscribed' => $unsubscribed,
                'total' => $verified + $pending + $unsubscribed,
            ],
            'growth' => $growth,
            'campaigns' => $campaignStats,
            'recent_events' => $recentEvents,
            'engagement' => [
                'emails_sent' => (int) ($totals->sent ?? 0),
                'opens' => (int) ($totals->opens ?? 0),
                'clicks' => (int) ($totals->clicks ?? 0),
                'avg_open_rate' => ($totals->sent ?? 0) > 0
                    ? round(((int) $totals->opens / (int) $totals->sent) * 100, 1)
                    : 0,
                'avg_click_rate' => ($totals->sent ?? 0) > 0
                    ? round(((int) $totals->clicks / (int) $totals->sent) * 100, 1)
                    : 0,
            ],
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function preferenceCategories(): array
    {
        return ArticleCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (ArticleCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $preferences
     * @return array{categories: list<string>}|null
     */
    private function normalizePreferences(mixed $preferences): ?array
    {
        if ($preferences === null) {
            return null;
        }

        if (is_array($preferences) && array_is_list($preferences)) {
            return ['categories' => array_values(array_filter(array_map('strval', $preferences)))];
        }

        if (is_array($preferences) && isset($preferences['categories']) && is_array($preferences['categories'])) {
            return ['categories' => array_values(array_filter(array_map('strval', $preferences['categories'])))];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function campaignPayload(array $data): array
    {
        $segments = $data['segments'] ?? null;
        if (is_array($segments) && isset($data['category_slugs'])) {
            $segments['category_slugs'] = $data['category_slugs'];
        } elseif (isset($data['category_slugs'])) {
            $segments = ['category_slugs' => $data['category_slugs']];
        }

        $payload = [
            'title' => $data['title'],
            'subject' => $data['subject'],
            'content_html' => $data['content_html'],
            'status' => $data['status'] ?? 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'segments' => $segments,
        ];

        if ($this->hasColumn('newsletter_campaigns', 'preview_text')) {
            $payload['preview_text'] = $data['preview_text'] ?? null;
        }

        if ($this->hasColumn('newsletter_campaigns', 'audience_type')) {
            $payload['audience_type'] = $data['audience_type'] ?? 'all';
        }

        if ($this->hasColumn('newsletter_campaigns', 'premium_only')) {
            $payload['premium_only'] = (bool) ($data['premium_only'] ?? false);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriberPayload(
        array $data,
        ?User $user,
        string $verificationToken,
        string $unsubscribeToken,
    ): array {
        $payload = [
            'name' => $data['name'] ?? null,
            'status' => 'pending',
            'preferences' => $this->normalizePreferences($data['preferences'] ?? null),
            'verification_token' => $verificationToken,
            'unsubscribe_token' => $unsubscribeToken,
            'verified_at' => null,
            'unsubscribed_at' => null,
        ];

        if ($this->hasColumn('newsletter_subscribers', 'user_id')) {
            $payload['user_id'] = $user?->id;
        }

        if ($this->hasColumn('newsletter_subscribers', 'source')) {
            $payload['source'] = $data['source'] ?? 'website';
        }

        if ($this->hasColumn('newsletter_subscribers', 'is_premium')) {
            $payload['is_premium'] = (bool) ($user && ($user->hasRole('premium') || $user->hasRole('member')));
        }

        if ($this->hasColumn('newsletter_subscribers', 'audience_tags')) {
            $payload['audience_tags'] = $data['audience_tags'] ?? null;
        }

        return $payload;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        if (!array_key_exists($key, self::$columnCache)) {
            self::$columnCache[$key] = Schema::hasColumn($table, $column);
        }

        return self::$columnCache[$key];
    }
}
