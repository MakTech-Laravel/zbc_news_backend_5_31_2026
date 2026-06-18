<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Newsletter\NewsletterService;

class NotificationPreferenceService
{
    public function __construct(
        private readonly NewsletterService $newsletterService,
    ) {}

    public function getOrCreate(User $user): NotificationPreference
    {
        $preference = NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            ['daily_newsletter' => false],
        );

        $preference->daily_newsletter = $this->newsletterService->isUserSubscribed($user);
        $preference->save();

        return $preference->fresh();
    }

    public function update(User $user, array $data): NotificationPreference
    {
        if (array_key_exists('daily_newsletter', $data)) {
            $this->newsletterService->syncUserSubscription($user, (bool) $data['daily_newsletter']);
        }

        $preference = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $data,
        );

        $preference->daily_newsletter = $this->newsletterService->isUserSubscribed($user);
        $preference->save();

        return $preference->fresh();
    }
}
