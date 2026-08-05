<?php

namespace App\Services\Newsletter;

use App\Jobs\SendNewsletterDeliveryStatusAdminEmailJob;
use App\Models\NewsletterSubscriber;
use App\Services\AdminNotificationPreferenceService;
use App\Services\UserNotificationService;
use App\Support\MailSender;
use Illuminate\Support\Facades\Mail;

class NewsletterDeliveryStatusAdminNotifier
{
    /** @var list<string> */
    public const ALERTABLE = ['delivered', 'failed', 'bounced', 'unsubscribed'];

    public function __construct(
        private readonly AdminNotificationPreferenceService $adminNotificationPreferences,
        private readonly UserNotificationService $userNotificationService,
    ) {}

    public function notify(
        NewsletterSubscriber $subscriber,
        string $status,
        ?int $eventId = null,
        ?string $rawEvent = null,
    ): void {
        if (! in_array($status, self::ALERTABLE, true)) {
            return;
        }

        $this->userNotificationService->dispatchNewsletterDeliveryStatusAdminNotifications(
            $subscriber,
            $status,
            $eventId,
            $rawEvent,
        );

        SendNewsletterDeliveryStatusAdminEmailJob::dispatch(
            $subscriber->id,
            $status,
            $eventId,
            $rawEvent,
        );
    }

    public function sendAdminEmail(
        NewsletterSubscriber $subscriber,
        string $status,
        ?int $eventId = null,
        ?string $rawEvent = null,
    ): void {
        if (! in_array($status, self::ALERTABLE, true)) {
            return;
        }

        $admins = $this->adminNotificationPreferences->emailRecipients(
            AdminNotificationPreferenceService::EVENT_NEWSLETTER_DELIVERY,
        );

        if ($admins->isEmpty()) {
            return;
        }

        $siteName = MailSender::name();
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $adminUrl = $frontendUrl.'/admin/newsletters';
        $label = $this->statusLabel($status);
        $subscriberLabel = $subscriber->name ?: $subscriber->email;

        $subject = "Newsletter {$label} — {$subscriber->email}";
        $html = view('emails.newsletter-delivery-status-admin', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'statusLabel' => $label,
            'status' => $status,
            'subscriberName' => $subscriberLabel,
            'subscriberEmail' => $subscriber->email,
            'rawEvent' => $rawEvent,
            'eventId' => $eventId,
            'adminUrl' => $adminUrl,
        ])->render();

        foreach ($admins as $admin) {
            try {
                Mail::html($html, function ($message) use ($admin, $subject, $siteName): void {
                    $message->to((string) $admin->email, (string) $admin->name)
                        ->subject($subject)
                        ->from(MailSender::address(), $siteName);
                });
            } catch (\Throwable) {
                // Keep delivery-status flow running if admin mail transport fails.
            }
        }
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'delivered' => 'delivered',
            'failed' => 'failed',
            'bounced' => 'bounced',
            'unsubscribed' => 'unsubscribed',
            default => $status,
        };
    }
}
