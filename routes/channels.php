<?php

use Illuminate\Support\Facades\Broadcast;

/*
 * Default Laravel private user channel
 * Used by: subscribeToUserNotifications → App.Models.User.{userId}
 * Frontend: echo.private('App.Models.User.5').listen('.notification.created', ...)
 */
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * PUBLIC CHANNEL — no auth needed
 * Frontend: echo.channel('news-updates').listen('.NewsPublished', ...)
 */
Broadcast::channel('news-updates', function () {
    return true;
});

/*
 * PRIVATE per-user notification channel (alternative naming)
 * Frontend: echo.private('notifications.5').listen(...)
 */
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/*
 * Admin-only channel
 * Frontend: echo.private('admin.dashboard').listen(...)
 */
Broadcast::channel('admin.dashboard', function ($user) {
    return $user->hasRole('admin');
});