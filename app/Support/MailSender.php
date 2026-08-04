<?php

namespace App\Support;

class MailSender
{
    /**
     * Display name shown in inboxes for every outbound message.
     */
    public static function name(): string
    {
        $name = trim((string) config('mail.from.name', ''));

        return $name !== '' ? $name : 'ZBC News';
    }

    /**
     * From email address (SMTP / provider identity).
     */
    public static function address(): string
    {
        $address = trim((string) config('mail.from.address', ''));

        return $address !== '' ? $address : 'hello@example.com';
    }
}
