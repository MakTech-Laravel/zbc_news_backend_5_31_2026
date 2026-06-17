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
        $provider = strtolower((string) ($settings->newsletter_provider ?: 'smtp'));

        return match ($provider) {
            'resend' => new ResendEmailProvider((string) $settings->resend_api_key),
            'brevo' => new BrevoEmailProvider((string) $settings->brevo_api_key),
            'mailchimp' => new MailchimpEmailProvider((string) $settings->mailchimp_api_key),
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
            'email' => (string) ($settings->newsletter_from_email ?: config('newsletter.default_from_email')),
            'name' => (string) ($settings->newsletter_from_name ?: config('newsletter.default_from_name')),
        ];
    }
}
