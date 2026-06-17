<?php

namespace App\Services\Newsletter\EmailProviders;

use App\Contracts\Newsletter\EmailProviderInterface;
use Illuminate\Support\Facades\Mail;

class SmtpEmailProvider implements EmailProviderInterface
{
    public function send(array $payload): void
    {
        Mail::html($payload['html'], function ($message) use ($payload): void {
            $message->to($payload['to'], $payload['to_name'] ?? null)
                ->subject($payload['subject'])
                ->from($payload['from_email'], $payload['from_name']);
        });
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }
}
