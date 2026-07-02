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
        $subject = "Verify your {$this->siteName} newsletter subscription";

        return $this
            ->subject($subject)
            ->view('emails.newsletter-verification', [
                'subjectLine' => $subject,
                'siteName' => $this->siteName,
                'verifyUrl' => $this->verifyUrl,
            ]);
    }
}
