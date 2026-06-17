<?php

namespace App\Contracts\Newsletter;

interface EmailProviderInterface
{
    /**
     * @param  array{to: string, to_name?: string|null, subject: string, html: string, from_email: string, from_name: string}  $payload
     */
    public function send(array $payload): void;

    public function supportsWebhooks(): bool;
}
