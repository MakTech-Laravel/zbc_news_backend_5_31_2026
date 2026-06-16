<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $verifyUrl,
        public string $siteName = 'ZBC News',
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Verify your {$this->siteName} newsletter subscription")
            ->html(
                "<p>Thanks for subscribing to {$this->siteName}.</p>" .
                "<p>Please verify your email by clicking the link below:</p>" .
                "<p><a href=\"{$this->verifyUrl}\">Verify subscription</a></p>"
            );
    }
}

