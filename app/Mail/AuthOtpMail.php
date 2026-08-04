<?php

namespace App\Mail;

use App\Services\AuthOtpService;
use App\Support\MailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AuthOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $purpose,
        public string $siteName = 'ZBC News',
    ) {}

    public function build(): self
    {
        $isPasswordReset = $this->purpose === AuthOtpService::PURPOSE_PASSWORD_RESET;
        $siteName = $this->siteName !== '' ? $this->siteName : MailSender::name();

        $subject = $isPasswordReset
            ? "Reset your {$siteName} password"
            : "Verify your {$siteName} email";

        return $this
            ->from(MailSender::address(), MailSender::name())
            ->subject($subject)
            ->view('emails.auth-otp', [
                'subjectLine' => $subject,
                'siteName' => $siteName,
                'otp' => $this->otp,
                'heading' => $isPasswordReset ? 'Password Reset' : 'Email Verification',
                'intro' => $isPasswordReset
                    ? "We received a request to reset your password for {$siteName}. Use the code below to continue."
                    : "Thank you for registering with {$siteName}. Verify your email address using the code below.",
                'codeLabel' => $isPasswordReset ? 'password reset code' : 'verification code',
            ]);
    }
}
