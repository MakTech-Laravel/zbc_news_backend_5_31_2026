<?php

namespace App\Mail;

use App\Support\MailSender;
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
        $siteName = $this->siteName !== '' ? $this->siteName : MailSender::name();
        $subject = "Verify your {$siteName} newsletter subscription";

        return $this
            ->from(MailSender::address(), MailSender::name())
            ->subject($subject)
            ->view('emails.newsletter-verification', [
                'subjectLine' => $subject,
                'siteName' => $siteName,
                'verifyUrl' => $this->verifyUrl,
            ]);
    }
}
