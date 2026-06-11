<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferenceService
{
    /**
     * Create a new class instance.
     */
    public function __construct(){}

    public function getOrCreate(User $user): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id]
        );
    }

    public function update(User $user, array $data): NotificationPreference
    {
        $preference = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        return $preference->fresh();
    }
}
