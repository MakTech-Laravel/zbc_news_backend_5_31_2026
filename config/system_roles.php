<?php

/**
 * Core system roles that must not be deleted.
 * Custom roles created in admin are deletable unless explicitly marked protected.
 */
return [
    'protected' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'editor' => 'Editor',
        'writer' => 'Writer',
        'moderator' => 'Moderator',
        'subscriber' => 'Subscriber',
        // Legacy reader-panel slug — same audience as Subscriber.
        'user' => 'Subscriber',
    ],
];
