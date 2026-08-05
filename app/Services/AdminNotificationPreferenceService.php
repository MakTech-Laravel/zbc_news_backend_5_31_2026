<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class AdminNotificationPreferenceService
{
    public const CHANNEL_DASHBOARD = 'dashboard';

    public const CHANNEL_EMAIL = 'email';

    public const EVENT_NEWSLETTER_SUBSCRIPTION = 'newsletter_subscription';

    public const EVENT_NEWSLETTER_CAMPAIGN = 'newsletter_campaign';

    public const EVENT_NEWSLETTER_DELIVERY = 'newsletter_delivery';

    public const EVENT_ACCOUNT_ACTIVITY = 'account_activity';

    public const EVENT_COMMENT_MODERATION = 'comment_moderation';

    public const EVENT_CONTACT_INQUIRY = 'contact_inquiry';

    public const EVENT_CAREER_APPLICATION = 'career_application';

    public const EVENT_TASK_FAILURE = 'task_failure';

    public const EVENT_SECURITY_ALERT = 'security_alert';

    public const DEFAULTS = [
        self::EVENT_NEWSLETTER_SUBSCRIPTION => [
            self::CHANNEL_DASHBOARD => true,
            self::CHANNEL_EMAIL => true,
        ],
        self::EVENT_NEWSLETTER_CAMPAIGN => [
            self::CHANNEL_DASHBOARD => true,
            self::CHANNEL_EMAIL => true,
        ],
        self::EVENT_NEWSLETTER_DELIVERY => [
            self::CHANNEL_DASHBOARD => true,
            self::CHANNEL_EMAIL => true,
        ],
        self::EVENT_ACCOUNT_ACTIVITY => [
            self::CHANNEL_DASHBOARD => true,
            self::CHANNEL_EMAIL => true,
        ],
        self::EVENT_COMMENT_MODERATION => [
            self::CHANNEL_DASHBOARD => true,
            self::CHANNEL_EMAIL => true,
        ],
        self::EVENT_CONTACT_INQUIRY => [
            self::CHANNEL_DASHBOARD => true,
            self::CHANNEL_EMAIL => true,
        ],
        self::EVENT_CAREER_APPLICATION => [
            self::CHANNEL_DASHBOARD => true,
            self::CHANNEL_EMAIL => true,
        ],
        self::EVENT_TASK_FAILURE => [
            self::CHANNEL_DASHBOARD => true,
            self::CHANNEL_EMAIL => true,
        ],
        self::EVENT_SECURITY_ALERT => [
            self::CHANNEL_DASHBOARD => true,
            self::CHANNEL_EMAIL => true,
        ],
    ];

    public function __construct(
        private readonly SiteSettingsService $siteSettingsService,
    ) {}

    /**
     * @return array<string, array{dashboard: bool, email: bool}>
     */
    public function all(): array
    {
        $stored = $this->siteSettingsService->getOrDefault()->admin_notification_channels;

        if (! is_array($stored)) {
            return self::DEFAULTS;
        }

        $settings = self::DEFAULTS;

        foreach (self::DEFAULTS as $event => $channels) {
            $saved = is_array($stored[$event] ?? null) ? $stored[$event] : [];
            $settings[$event] = [
                self::CHANNEL_DASHBOARD => array_key_exists(self::CHANNEL_DASHBOARD, $saved)
                    ? (bool) $saved[self::CHANNEL_DASHBOARD]
                    : $channels[self::CHANNEL_DASHBOARD],
                self::CHANNEL_EMAIL => array_key_exists(self::CHANNEL_EMAIL, $saved)
                    ? (bool) $saved[self::CHANNEL_EMAIL]
                    : $channels[self::CHANNEL_EMAIL],
            ];
        }

        return $settings;
    }

    public function enabled(string $event, string $channel): bool
    {
        return (bool) ($this->all()[$event][$channel] ?? false);
    }

    /**
     * @return SupportCollection<int, int>
     */
    public function dashboardRecipientIds(string $event): SupportCollection
    {
        if (! $this->enabled($event, self::CHANNEL_DASHBOARD)) {
            return collect();
        }

        return $this->staffQuery()->pluck('id');
    }

    /**
     * @return Collection<int, User>
     */
    public function emailRecipients(string $event): Collection
    {
        if (! $this->enabled($event, self::CHANNEL_EMAIL)) {
            return new Collection();
        }

        return $this->staffQuery()->get(['users.id', 'users.email', 'users.name']);
    }

    /**
     * @param  array<string, array{dashboard: bool, email: bool}>  $settings
     * @return array<string, array{dashboard: bool, email: bool}>
     */
    public function update(array $settings): array
    {
        $normalized = [];

        foreach (self::DEFAULTS as $event => $defaults) {
            $channels = $settings[$event] ?? $defaults;
            $normalized[$event] = [
                self::CHANNEL_DASHBOARD => (bool) ($channels[self::CHANNEL_DASHBOARD] ?? false),
                self::CHANNEL_EMAIL => (bool) ($channels[self::CHANNEL_EMAIL] ?? false),
            ];
        }

        $this->siteSettingsService->createOrUpdate([
            'admin_notification_channels' => $normalized,
        ]);

        return $normalized;
    }

    private function staffQuery()
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', '!=', 'user'));
    }
}
