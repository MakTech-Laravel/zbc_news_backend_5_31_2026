<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case BREAKING = 'breaking';
    case TOPIC = 'topic';
    case SYSTEM = 'system';
    case SOCIAL = 'social';
    case SAVED = 'saved';
}
