<?php

namespace App\Services\Newsletter;

use App\Contracts\Newsletter\EmailProviderInterface;
use App\Services\Newsletter\EmailProviders\BrevoEmailProvider;
use App\Services\Newsletter\EmailProviders\MailchimpEmailProvider;
use App\Services\Newsletter\EmailProviders\ResendEmailProvider;
use App\Services\Newsletter\EmailProviders\SmtpEmailProvider;
use App\Services\SiteSettingsService;

class NewsletterEmailProviderFactory
{
    public function __construct(
        private readonly SiteSettingsService $siteSettingsService,
    ) {}

    public function make(): EmailProviderInterface
    {
        $settings = $this->siteSettingsService->getOrDefault();
        $provider = strtolower((string) ($settings->getAttribute('newsletter_provider') ?: 'smtp'));

        return match ($provider) {
            'resend' => filled($settings->getAttribute('resend_api_key'))
                ? new ResendEmailProvider((string) $settings->getAttribute('resend_api_key'))
                : new SmtpEmailProvider(),
            'brevo' => filled($settings->getAttribute('brevo_api_key'))
                ? new BrevoEmailProvider((string) $settings->getAttribute('brevo_api_key'))
                : new SmtpEmailProvider(),
            'mailchimp' => filled($settings->getAttribute('mailchimp_api_key'))
                ? new MailchimpEmailProvider((string) $settings->getAttribute('mailchimp_api_key'))
                : new SmtpEmailProvider(),
            default => new SmtpEmailProvider(),
        };
    }

    /**
     * @return array{email: string, name: string}
     */
    public function fromAddress(): array
    {
        $settings = $this->siteSettingsService->getOrDefault();

        return [
            'email' => (string) ($settings->getAttribute('newsletter_from_email') ?: config('newsletter.default_from_email')),
            'name' => (string) ($settings->getAttribute('newsletter_from_name') ?: config('newsletter.default_from_name')),
        ];
    }
}
