<?php

namespace App\Services\Newsletter;

use App\Enums\ArticleCategoryStatus;
use App\Jobs\SendNewsletterAdminSubscriptionEmailJob;
use App\Jobs\SendNewsletterCampaignJob;
use App\Jobs\SendNewsletterVerificationEmailJob;
use App\Models\ArticleCategory;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterEvent;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NewsletterService
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    public function __construct(
        private readonly NewsletterEmailProviderFactory $providerFactory,
        private readonly NewsletterTrackingService $trackingService,
        private readonly NewsletterContentFormatter $contentFormatter,
        private readonly UserNotificationService $userNotificationService,
    ) {}

    public function subscribe(array $data, ?User $user = null): NewsletterSubscriber
    {
        $email = strtolower(trim((string) $data['email']));
        $existing = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($existing && $existing->status === 'verified') {
            $existing->update([
                'name' => $data['name'] ?? $existing->name,
                'preferences' => $this->normalizePreferences($data['preferences'] ?? null) ?? $existing->preferences,
            ]);

            return $existing->fresh();
        }

        $verificationToken = ($existing && filled($existing->verification_token))
            ? $existing->verification_token
            : Str::random(64);
        $unsubscribeToken = ($existing && filled($existing->unsubscribe_token))
            ? $existing->unsubscribe_token
            : Str::random(64);

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $email],
            $this->subscriberPayload($data, $user, $verificationToken, $unsubscribeToken),
        );

        $this->queueVerificationEmail($subscriber);

        $this->userNotificationService->dispatchNewsletterSubscriptionAdminNotifications($subscriber);
        $this->queueAdminSubscriptionNotificationEmail($subscriber);

        return $subscriber;
    }

    public function queueVerificationEmail(NewsletterSubscriber $subscriber): void
    {
        SendNewsletterVerificationEmailJob::dispatch($subscriber->id);
    }

    public function queueAdminSubscriptionNotificationEmail(
        NewsletterSubscriber $subscriber,
        bool $verified = false,
    ): void {
        SendNewsletterAdminSubscriptionEmailJob::dispatch($subscriber->id, $verified);
    }

    public function sendVerificationEmail(NewsletterSubscriber $subscriber): void
    {
        $verifyUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/')
            .'/newsletter/verify?token='.$subscriber->verification_token;

        $from = $this->providerFactory->fromAddress();
        $siteName = $from['name'] ?: 'ZBC News';
        $subject = "Verify your {$siteName} newsletter subscription";
        $html = view('emails.newsletter-verification', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'verifyUrl' => $verifyUrl,
        ])->render();

        try {
            $this->providerFactory->make()->send([
                'to' => $subscriber->email,
                'to_name' => $subscriber->name,
                'subject' => $subject,
                'html' => $html,
                'from_email' => $from['email'],
                'from_name' => $from['name'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Newsletter verification email could not be sent.', [
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function verify(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('verification_token', trim($token))
            ->first();

        if (! $subscriber) {
            return null;
        }

        if ($subscriber->status === 'verified') {
            return $subscriber;
        }

        $subscriber->update([
            'status' => 'verified',
            'verified_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $subscriber = $subscriber->fresh();

        $this->userNotificationService->dispatchNewsletterSubscriptionAdminNotifications(
            $subscriber,
            verified: true,
        );
        $this->queueAdminSubscriptionNotificationEmail($subscriber, verified: true);

        return $subscriber;
    }

    public function sendAdminSubscriptionNotificationEmail(
        NewsletterSubscriber $subscriber,
        bool $verified = false,
    ): void {
        $admins = User::query()
            ->role(['admin', 'super_admin'])
            ->get(['id', 'email', 'name']);

        if ($admins->isEmpty()) {
            return;
        }

        $from = $this->providerFactory->fromAddress();
        $siteName = $from['name'] ?: 'ZBC News';
        $adminUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/')
            .'/admin/newsletters';
        $categories = $this->formatSubscriberCategories($subscriber);

        $subject = $verified
            ? "Newsletter subscription verified — {$subscriber->email}"
            : "New newsletter subscription — {$subscriber->email}";

        $html = view('emails.newsletter-admin-subscription', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'subscriber' => $subscriber,
            'verified' => $verified,
            'categories' => $categories,
            'adminUrl' => $adminUrl,
        ])->render();

        $provider = $this->providerFactory->make();

        foreach ($admins as $admin) {
            try {
                $provider->send([
                    'to' => $admin->email,
                    'to_name' => $admin->name,
                    'subject' => $subject,
                    'html' => $html,
                    'from_email' => $from['email'],
                    'from_name' => $from['name'],
                ]);
            } catch (\Throwable) {
                // Keep subscription flow running if admin mail transport fails.
            }
        }
    }

    /**
     * @return array{email: string, status: string}|null
     */
    public function previewVerification(string $token): ?array
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('verification_token', trim($token))
            ->first();

        if (! $subscriber) {
            return null;
        }

        return [
            'email' => $subscriber->email,
            'status' => $subscriber->status,
        ];
    }

    public function unsubscribe(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('unsubscribe_token', trim($token))
            ->first();

        if (! $subscriber) {
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

        if (! $subscriber) {
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
        if (! in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid subscriber status.');
        }

        $subscriber = NewsletterSubscriber::query()->find($id);
        if (! $subscriber) {
            return null;
        }

        if ($subscriber->status === 'verified') {
            if ($status === 'verified') {
                return $subscriber;
            }

            if ($status === 'pending') {
                throw new \InvalidArgumentException('Verified subscribers cannot be set back to pending.');
            }
        }

        $updates = ['status' => $status];

        if ($status === 'verified') {
            $updates['verified_at'] = now();
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
        if (! $subscriber || $subscriber->status !== 'pending') {
            return null;
        }

        if (empty($subscriber->verification_token)) {
            $subscriber->update(['verification_token' => Str::random(64)]);
            $subscriber = $subscriber->fresh();
        }

        $this->queueVerificationEmail($subscriber);

        return $subscriber;
    }

    public function isUserSubscribed(User $user): bool
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('email', strtolower(trim($user->email)))
            ->first();

        return $subscriber !== null && $subscriber->status === 'verified';
    }

    public function syncUserSubscription(User $user, bool $subscribed): NewsletterSubscriber
    {
        $email = strtolower(trim($user->email));

        if (! $subscribed) {
            $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

            if ($subscriber) {
                $subscriber->update([
                    'status' => 'unsubscribed',
                    'unsubscribed_at' => now(),
                ]);

                NewsletterEvent::query()->create([
                    'newsletter_subscriber_id' => $subscriber->id,
                    'event_type' => 'unsubscribe',
                    'meta' => ['source' => 'profile'],
                ]);
            }

            return $subscriber ?? NewsletterSubscriber::query()->make(['email' => $email, 'status' => 'unsubscribed']);
        }

        $verificationToken = Str::random(64);
        $unsubscribeToken = Str::random(64);

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $email],
            array_merge(
                $this->subscriberPayload([
                    'email' => $email,
                    'name' => $user->name,
                    'source' => 'profile',
                ], $user, $verificationToken, $unsubscribeToken),
                [
                    'status' => 'verified',
                    'verified_at' => now(),
                    'unsubscribed_at' => null,
                    'verification_token' => null,
                    'user_id' => $user->id,
                ],
            ),
        );

        $subscriber = $subscriber->fresh();

        $this->userNotificationService->dispatchNewsletterSubscriptionAdminNotifications(
            $subscriber,
            verified: true,
        );

        return $subscriber;
    }

    public function listCampaigns(): LengthAwarePaginator
    {
        $this->processDueScheduledCampaigns();

        return NewsletterCampaign::query()->latest('id')->paginate(20);
    }

    public function getCampaign(int $id): ?NewsletterCampaign
    {
        $this->processDueScheduledCampaigns();

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
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw new \RuntimeException('Only draft campaigns can be scheduled.');
        }

        $scheduledAt = $scheduledAt->clone()->utc();

        // Past/due schedule → send immediately (same expectation as articles).
        if ($scheduledAt->lessThanOrEqualTo(now())) {
            $campaign->scheduled_at = $scheduledAt;

            return $this->dispatchCampaign($campaign);
        }

        $campaign->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);

        return $campaign->fresh();
    }

    public function dispatchCampaign(NewsletterCampaign $campaign, bool $touchTimestamps = true): NewsletterCampaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw new \RuntimeException('Campaign cannot be sent in its current state.');
        }

        $recipientCount = count($this->resolveCampaignRecipientIds($campaign));

        if ($recipientCount === 0) {
            throw new \RuntimeException(
                'No eligible recipients for this campaign. Users may be unsubscribed or the audience is empty.',
            );
        }

        $attributes = [
            'status' => 'sending',
            'scheduled_at' => $campaign->scheduled_at ?? now(),
            'subscriber_count' => $recipientCount,
            'failed_count' => 0,
        ];

        if ($touchTimestamps) {
            $campaign->update($attributes);
        } else {
            $this->updateCampaignWithoutTouchingTimestamp($campaign, $attributes);
        }

        SendNewsletterCampaignJob::dispatch($campaign->id);

        return $campaign->fresh();
    }

    public function processDueScheduledCampaigns(): int
    {
        $count = 0;

        NewsletterCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->each(function (NewsletterCampaign $campaign) use (&$count): void {
                try {
                    $this->dispatchCampaign($campaign, touchTimestamps: false);
                    $count++;
                } catch (\RuntimeException $exception) {
                    Log::warning('Scheduled newsletter campaign could not be sent.', [
                        'campaign_id' => $campaign->id,
                        'message' => $exception->getMessage(),
                    ]);

                    $this->updateCampaignWithoutTouchingTimestamp($campaign, [
                        'status' => 'draft',
                        'scheduled_at' => null,
                    ]);
                }
            });

        return $count;
    }

    public function recipientsQuery(NewsletterCampaign $campaign): Builder
    {
        if ($campaign->premium_only) {
            return NewsletterSubscriber::query()
                ->where('status', 'verified');
        }

        return NewsletterSubscriber::query()
            ->whereIn('status', ['pending', 'verified']);
    }

    /**
     * @return array{count: int, breakdown: array{subscribers: int, users: int}}
     */
    public function countEligibleRecipients(bool $premiumOnly): array
    {
        if ($premiumOnly) {
            $subscribers = NewsletterSubscriber::query()
                ->where('status', 'verified')
                ->count();

            return [
                'count' => $subscribers,
                'breakdown' => [
                    'subscribers' => $subscribers,
                    'users' => 0,
                ],
            ];
        }

        $subscribers = NewsletterSubscriber::query()
            ->whereIn('status', ['pending', 'verified'])
            ->count();

        $unsubscribedEmails = NewsletterSubscriber::query()
            ->where('status', 'unsubscribed')
            ->pluck('email')
            ->map(fn (string $email) => strtolower(trim($email)))
            ->all();

        $users = User::query()
            ->role('user')
            ->get(['id', 'email']);

        $eligibleUsers = $users->filter(function (User $user) use ($unsubscribedEmails): bool {
            $email = strtolower(trim((string) $user->email));

            return $email !== '' && ! in_array($email, $unsubscribedEmails, true);
        })->count();

        return [
            'count' => $subscribers + $eligibleUsers,
            'breakdown' => [
                'subscribers' => $subscribers,
                'users' => $eligibleUsers,
            ],
        ];
    }

    /**
     * @return array{count: int, breakdown: array{subscribers: int, users: int}}
     */
    public function countEligibleRecipientsForCampaign(NewsletterCampaign $campaign): array
    {
        return $this->countEligibleRecipients((bool) $campaign->premium_only);
    }

    /**
     * @return list<int>
     */
    public function resolveCampaignRecipientIds(NewsletterCampaign $campaign): array
    {
        if ($campaign->premium_only) {
            return $this->recipientsQuery($campaign)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $recipientIds = $this->recipientsQuery($campaign)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $unsubscribedEmails = NewsletterSubscriber::query()
            ->where('status', 'unsubscribed')
            ->pluck('email')
            ->map(fn (string $email) => strtolower(trim($email)))
            ->all();

        User::query()
            ->role('user')
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use (&$recipientIds, $unsubscribedEmails): void {
                $email = strtolower(trim((string) $user->email));

                if ($email === '' || in_array($email, $unsubscribedEmails, true)) {
                    return;
                }

                $subscriber = $this->ensureSubscriberForUser($user, preservePendingStatus: true);

                if ($subscriber->status === 'unsubscribed') {
                    return;
                }

                $recipientIds[] = (int) $subscriber->id;
            });

        return $recipientIds;
    }

    public function ensureSubscriberForUser(User $user, bool $preservePendingStatus = false): NewsletterSubscriber
    {
        $email = strtolower(trim((string) $user->email));

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber) {
            if ($subscriber->status === 'unsubscribed') {
                return $subscriber;
            }

            $updates = [];

            if ($this->hasColumn('newsletter_subscribers', 'user_id') && $subscriber->user_id !== $user->id) {
                $updates['user_id'] = $user->id;
            }

            if (! $preservePendingStatus && $subscriber->status !== 'verified' && $subscriber->status !== 'unsubscribed') {
                $updates['status'] = 'verified';
                $updates['verified_at'] = now();
                $updates['unsubscribed_at'] = null;
            }

            if (empty($subscriber->unsubscribe_token)) {
                $updates['unsubscribe_token'] = Str::random(64);
            }

            if ($updates !== []) {
                $subscriber->update($updates);
            }

            return $subscriber->fresh();
        }

        $payload = [
            'email' => $email,
            'name' => $user->name,
            'status' => 'verified',
            'verified_at' => now(),
            'verification_token' => Str::random(64),
            'unsubscribe_token' => Str::random(64),
            'unsubscribed_at' => null,
        ];

        if ($this->hasColumn('newsletter_subscribers', 'user_id')) {
            $payload['user_id'] = $user->id;
        }

        if ($this->hasColumn('newsletter_subscribers', 'source')) {
            $payload['source'] = 'account';
        }

        if ($this->hasColumn('newsletter_subscribers', 'is_premium')) {
            $payload['is_premium'] = $user->hasRole('premium') || $user->hasRole('member');
        }

        return NewsletterSubscriber::query()->create($payload);
    }

    public function buildEmailHtml(NewsletterCampaign $campaign, NewsletterSubscriber $subscriber): string
    {
        $from = $this->providerFactory->fromAddress();
        $siteName = $from['name'] ?: 'ZBC News';
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        $prepared = $this->contentFormatter->prepareBody((string) ($campaign->content_html ?? ''));
        $content = $this->trackingService->wrapHtmlLinks(
            $prepared['html'],
            $campaign->id,
            $subscriber->id,
        );

        $html = view('emails.newsletter-campaign', [
            'subjectLine' => $campaign->subject,
            'siteName' => $siteName,
            'title' => $campaign->title,
            'previewText' => $campaign->preview_text,
            'content' => $content,
            'subscriberName' => $subscriber->name,
            'preferencesUrl' => $frontendUrl.'/newsletter/preferences?token='.$subscriber->unsubscribe_token,
            'unsubscribeUrl' => $frontendUrl.'/newsletter/unsubscribe?token='.$subscriber->unsubscribe_token,
        ])->render();

        return $this->trackingService->injectTrackingPixel($html, $campaign->id, $subscriber->id);
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
        if (! $this->hasColumn('newsletter_campaigns', 'failed_count')) {
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
            ->where('status', ArticleCategoryStatus::ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'title', 'slug'])
            ->map(fn (ArticleCategory $category) => [
                'id' => $category->id,
                'name' => $category->title,
                'slug' => $category->slug,
            ])
            ->values()
            ->all();
    }

    /**
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
            'subject' => filled($data['subject'] ?? null) ? $data['subject'] : $data['title'],
            'content_html' => $data['content_html'],
            'status' => $data['status'] ?? 'draft',
            'scheduled_at' => $this->normalizeCampaignScheduledAt($data['scheduled_at'] ?? null),
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

    private function formatSubscriberCategories(NewsletterSubscriber $subscriber): string
    {
        $categories = $subscriber->preferences['categories'] ?? null;

        if (! is_array($categories) || count($categories) === 0) {
            return 'All categories';
        }

        return implode(', ', array_map('strval', $categories));
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        if (! array_key_exists($key, self::$columnCache)) {
            self::$columnCache[$key] = Schema::hasColumn($table, $column);
        }

        return self::$columnCache[$key];
    }

    private function normalizeCampaignScheduledAt(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->clone()->utc();
        }

        return Carbon::parse((string) $value)->utc();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateCampaignWithoutTouchingTimestamp(NewsletterCampaign $campaign, array $attributes): void
    {
        $campaign->timestamps = false;

        try {
            $campaign->update($attributes);
        } finally {
            $campaign->timestamps = true;
        }
    }
}
