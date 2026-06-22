<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * PUBLIC CHANNEL — anyone can subscribe, no auth needed
 * Frontend: echo.channel('news-updates').listen(...)
 */
Broadcast::channel('news-updates', function () {
    return true;
});

/*
 * PRIVATE CHANNEL — requires authenticated user via Passport token
 * Frontend: echo.private('notifications.5').listen(...)
 * The {userId} must match the authenticated user's ID
 */
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/*
 * Default Laravel private user channel
 * Frontend: echo.private('App.Models.User.5').listen(...)
 */
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Admin-only channel example
 * Frontend: echo.private('admin.dashboard').listen(...)
 */
Broadcast::channel('admin.dashboard', function ($user) {
    return $user->hasRole('admin');
});