<?php

namespace App\Enums;

enum NotificationIcon: string
{
    case BREAKING = 'breaking';
    case TECHNOLOGY = 'technology';
    case RECOMMENDED = 'recommended';
    case REPLY = 'reply';
    case SAVED = 'saved';
    case BUSINESS = 'business';
}
