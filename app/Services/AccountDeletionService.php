<?php

namespace App\Services;

use App\Jobs\SendAccountDeletionAdminEmailJob;
use App\Jobs\SendAccountDeletionCancelAdminEmailJob;
use App\Jobs\SendAccountDeletionCancelRequestedEmailJob;
use App\Jobs\SendAccountDeletionRequestedEmailJob;
use App\Jobs\SendAccountRestoredAdminEmailJob;
use App\Jobs\SendAccountRestoredEmailJob;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Models\UserInformation;
use App\Services\Newsletter\BrevoContactService;
use App\Support\MailSender;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AccountDeletionService
{
    public const GRACE_DAYS = 30;

    public function __construct(
        private readonly BrevoContactService $brevoContactService,
        private readonly UserNotificationService $userNotificationService,
    ) {}

    /**
     * @return array{user: User, scheduled_permanent_deletion_at: string}
     */
    public function requestDeletion(User $user, string $password): array
    {
        if (! $this->isReaderOnlyAccount($user)) {
            throw new \InvalidArgumentException('Only reader accounts can request self-service deletion.');
        }

        if ($user->isPermanentlyDeleted()) {
            throw new \InvalidArgumentException('This account has already been permanently deleted.');
        }

        if ($user->isPendingDeletion()) {
            throw new \InvalidArgumentException('A deletion request is already pending for this account.');
        }

        if (! Hash::check($password, (string) $user->password)) {
            throw new \InvalidArgumentException('The current password is incorrect.');
        }

        $scheduledAt = now()->addDays(self::GRACE_DAYS);
        $cancelToken = Str::random(64);
        $originalEmail = strtolower(trim((string) $user->email));

        $user->forceFill([
            'deletion_requested_at' => now(),
            'scheduled_permanent_deletion_at' => $scheduledAt,
            'deletion_cancel_token' => $cancelToken,
            'deletion_cancel_requested_at' => null,
            'permanently_deleted_at' => null,
        ])->save();

        $user->tokens()->delete();

        $this->unsubscribeNewsletterForEmail($originalEmail);

        SendAccountDeletionRequestedEmailJob::dispatch($user->id);
        $this->userNotificationService->dispatchAccountDeletionAdminNotifications($user->fresh());
        SendAccountDeletionAdminEmailJob::dispatch($user->id);

        return [
            'user' => $user->fresh(),
            'scheduled_permanent_deletion_at' => $scheduledAt->toIso8601String(),
        ];
    }

    public function isReaderOnlyAccount(User $user): bool
    {
        if (! $user->hasRole('user')) {
            return false;
        }

        return ! $user->hasAnyRole(['admin', 'super_admin', 'editor', 'author']);
    }

    /**
     * User submits a cancel request for admin review (does not restore access yet).
     */
    public function requestCancelDeletion(string $token): User
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('A valid cancellation token is required.');
        }

        $user = User::query()
            ->where('deletion_cancel_token', $token)
            ->whereNotNull('deletion_requested_at')
            ->whereNull('permanently_deleted_at')
            ->first();

        if (! $user) {
            throw new \InvalidArgumentException('Invalid or expired cancellation token.');
        }

        if ($user->hasDeletionCancelRequest()) {
            return $user;
        }

        $user->forceFill([
            'deletion_cancel_requested_at' => now(),
        ])->save();

        $user = $user->fresh();

        SendAccountDeletionCancelRequestedEmailJob::dispatch($user->id);
        $this->userNotificationService->dispatchAccountDeletionCancelAdminNotifications($user);
        SendAccountDeletionCancelAdminEmailJob::dispatch($user->id);

        return $user;
    }

    public function adminRestore(User $user): User
    {
        if ($user->isPermanentlyDeleted()) {
            throw new \InvalidArgumentException('This account has been permanently deleted and cannot be restored.');
        }

        if (! $user->isPendingDeletion()) {
            throw new \InvalidArgumentException('This account does not have a pending deletion request.');
        }

        $restored = $this->clearDeletionFlags($user);

        $this->userNotificationService->dispatchAccountRestoredUserNotification($restored);
        SendAccountRestoredEmailJob::dispatch($restored->id);
        $this->userNotificationService->dispatchAccountRestoredAdminNotifications($restored);
        SendAccountRestoredAdminEmailJob::dispatch($restored->id);

        return $restored;
    }

    public function permanentlyAnonymize(User $user): User
    {
        if ($user->isPermanentlyDeleted()) {
            return $user;
        }

        if ($user->hasDeletionCancelRequest()) {
            throw new \InvalidArgumentException('Accounts with a pending cancel request cannot be permanently deleted.');
        }

        $originalEmail = strtolower(trim((string) $user->email));
        $this->unsubscribeNewsletterForEmail($originalEmail);

        $user->tokens()->delete();

        $user->forceFill([
            'name' => 'Deleted User',
            'email' => 'deleted+'.$user->id.'@deleted.local',
            'slug' => User::generateUniqueSlug('deleted-user-'.$user->id, $user->id),
            'password' => Hash::make(Str::random(64)),
            'remember_token' => null,
            'email_verified_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'deletion_cancel_token' => null,
            'deletion_cancel_requested_at' => null,
            'deletion_requested_at' => $user->deletion_requested_at ?? now(),
            'scheduled_permanent_deletion_at' => $user->scheduled_permanent_deletion_at ?? now(),
            'permanently_deleted_at' => now(),
        ])->save();

        $info = UserInformation::query()->where('user_id', $user->id)->first();
        if ($info) {
            $info->forceFill([
                'profile_image' => null,
                'bio' => null,
                'region' => null,
                'public_title' => null,
                'social_links' => [],
            ])->save();
        }

        return $user->fresh();
    }

    /**
     * @return int Number of accounts anonymized
     */
    public function purgeDueAccounts(): int
    {
        $users = User::query()
            ->whereNull('permanently_deleted_at')
            ->whereNotNull('scheduled_permanent_deletion_at')
            ->where('scheduled_permanent_deletion_at', '<=', now())
            ->whereNull('deletion_cancel_requested_at')
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $this->permanentlyAnonymize($user);
            $count++;
        }

        return $count;
    }

    public function sendDeletionRequestedEmail(User $user): void
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $siteName = MailSender::name();
        $finalDate = $user->scheduled_permanent_deletion_at
            ? $user->scheduled_permanent_deletion_at->timezone(config('app.timezone'))->toFormattedDateString()
            : now()->addDays(self::GRACE_DAYS)->toFormattedDateString();

        $cancelUrl = $frontendUrl.'/account/cancel-deletion?token='.urlencode((string) $user->deletion_cancel_token);
        $subject = 'Account deletion request received';

        $html = view('emails.account-deletion-requested', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'userName' => $user->name,
            'finalDeletionDate' => $finalDate,
            'graceDays' => self::GRACE_DAYS,
            'cancelUrl' => $cancelUrl,
            'homeUrl' => $frontendUrl.'/',
        ])->render();

        \Illuminate\Support\Facades\Mail::html($html, function ($message) use ($user, $subject, $siteName): void {
            $message->to((string) $user->email, (string) $user->name)
                ->subject($subject)
                ->from(
                    MailSender::address(),
                    $siteName,
                );
        });
    }

    public function sendDeletionRequestedAdminEmail(User $user): void
    {
        $admins = User::query()
            ->role(['admin', 'super_admin'])
            ->get(['id', 'email', 'name']);

        if ($admins->isEmpty()) {
            return;
        }

        $siteName = MailSender::name();
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $adminUrl = $frontendUrl.'/admin/account-deletions';
        $finalDate = $user->scheduled_permanent_deletion_at
            ? $user->scheduled_permanent_deletion_at->timezone(config('app.timezone'))->toFormattedDateString()
            : now()->addDays(self::GRACE_DAYS)->toFormattedDateString();

        $subject = "Account deletion requested — {$user->email}";
        $html = view('emails.account-deletion-admin', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'userName' => $user->name,
            'userEmail' => $user->email,
            'finalDeletionDate' => $finalDate,
            'graceDays' => self::GRACE_DAYS,
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
                // Keep deletion flow running if admin mail transport fails.
            }
        }
    }

    public function sendDeletionCancelRequestedEmail(User $user): void
    {
        if (! filled($user->email)) {
            return;
        }

        $siteName = MailSender::name();
        $subject = 'Account deletion cancellation request received';

        $html = view('emails.account-deletion-cancel-requested', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'userName' => $user->name,
        ])->render();

        Mail::html($html, function ($message) use ($user, $subject, $siteName): void {
            $message->to((string) $user->email, (string) $user->name)
                ->subject($subject)
                ->from(MailSender::address(), $siteName);
        });
    }

    public function sendDeletionCancelAdminEmail(User $user): void
    {
        $admins = User::query()
            ->role(['admin', 'super_admin'])
            ->get(['id', 'email', 'name']);

        if ($admins->isEmpty()) {
            return;
        }

        $siteName = MailSender::name();
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $adminUrl = $frontendUrl.'/admin/account-deletions';

        $subject = "Deletion cancel requested — {$user->email}";
        $html = view('emails.account-deletion-cancel-admin', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'userName' => $user->name,
            'userEmail' => $user->email,
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
                // Keep cancel flow running if admin mail transport fails.
            }
        }
    }

    public function sendAccountRestoredEmail(User $user): void
    {
        if (! filled($user->email)) {
            return;
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $siteName = MailSender::name();
        $subject = 'Your account has been restored';

        $html = view('emails.account-restored', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'userName' => $user->name,
            'loginUrl' => $frontendUrl.'/login',
        ])->render();

        Mail::html($html, function ($message) use ($user, $subject, $siteName): void {
            $message->to((string) $user->email, (string) $user->name)
                ->subject($subject)
                ->from(MailSender::address(), $siteName);
        });
    }

    public function sendAccountRestoredAdminEmail(User $user): void
    {
        $admins = User::query()
            ->role(['admin', 'super_admin'])
            ->get(['id', 'email', 'name']);

        if ($admins->isEmpty()) {
            return;
        }

        $siteName = MailSender::name();
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $adminUrl = $frontendUrl.'/admin/account-deletions';

        $subject = "Account restored — {$user->email}";
        $html = view('emails.account-restored-admin', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'userName' => $user->name,
            'userEmail' => $user->email,
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
                // Keep restore flow running if admin mail transport fails.
            }
        }
    }

    private function clearDeletionFlags(User $user): User
    {
        $user->forceFill([
            'deletion_requested_at' => null,
            'scheduled_permanent_deletion_at' => null,
            'deletion_cancel_token' => null,
            'deletion_cancel_requested_at' => null,
            'permanently_deleted_at' => null,
        ])->save();

        return $user->fresh();
    }

    private function unsubscribeNewsletterForEmail(string $email): void
    {
        if ($email === '') {
            return;
        }

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();
        if (! $subscriber) {
            return;
        }

        if ($subscriber->status !== 'unsubscribed') {
            $subscriber->update([
                'status' => 'unsubscribed',
                'unsubscribed_at' => $subscriber->unsubscribed_at ?? now(),
            ]);
        }

        $this->brevoContactService->markUnsubscribed($subscriber->fresh());
    }
}
