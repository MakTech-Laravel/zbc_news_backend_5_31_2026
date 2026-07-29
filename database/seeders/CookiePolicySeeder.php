<?php

namespace Database\Seeders;

use App\Models\CookiePolicySetting;
use Illuminate\Database\Seeder;

class CookiePolicySeeder extends Seeder
{
    public function run(): void
    {
        CookiePolicySetting::query()->firstOrCreate([], self::defaultContent());
    }

    public static function defaultContent(): array
    {
        return [
            'hero_meta' => 'Last updated: June 1, 2026 · Version 2.3',
            'hero_description' => 'ZBC News uses cookies to keep you logged in, remember your preferences, understand how our journalism is read, and — with your consent — to serve relevant advertising. This page explains every cookie we use and gives you full control.',
            'preferences_intro' => 'Toggle optional cookie categories. Essential cookies cannot be disabled — they\'re required for ZBC News to work.',
            'categories' => [
                [
                    'id' => 'essential',
                    'title' => 'Strictly Essential',
                    'description' => 'These cookies are necessary for the website to function and cannot be disabled. They include session management, security tokens, and load balancing.',
                    'always_on' => true,
                    'default_enabled' => true,
                ],
                [
                    'id' => 'analytics',
                    'title' => 'Analytics',
                    'description' => 'Help us understand how readers use ZBC News — which articles are read, how traffic arrives, and where readers drop off. We use privacy-first analytics tools where possible.',
                    'always_on' => false,
                    'default_enabled' => true,
                ],
                [
                    'id' => 'preferences',
                    'title' => 'Preferences',
                    'description' => 'Remember your choices — display preferences, newsletter settings, region, and article view layout.',
                    'always_on' => false,
                    'default_enabled' => true,
                ],
                [
                    'id' => 'advertising',
                    'title' => 'Advertising & Targeting',
                    'description' => 'Used to show you relevant advertising content based on your reading history and inferred interests. Disabling these means you\'ll still see ads — they\'ll just be less relevant to you.',
                    'always_on' => false,
                    'default_enabled' => false,
                ],
            ],
            'browser_intro' => 'You can also manage cookies directly in your browser settings:',
            'browser_controls' => [
                ['browser' => 'Chrome', 'path' => 'Settings → Privacy & Security → Cookies and other site data'],
                ['browser' => 'Firefox', 'path' => 'Settings → Privacy & Security → Cookies and Site Data'],
                ['browser' => 'Safari', 'path' => 'Preferences → Privacy → Manage Website Data'],
                ['browser' => 'Edge', 'path' => 'Settings → Cookies and Site Permissions → Manage and delete cookies'],
            ],
            'faqs' => [
                [
                    'question' => 'What exactly is a cookie?',
                    'answer' => 'A cookie is a small text file stored on your device when you visit a website. It helps the site remember your preferences, keep you logged in, and understand how you use the service.',
                ],
                [
                    'question' => 'Can I use ZBC News without cookies?',
                    'answer' => 'Essential cookies are required for core functionality like signing in and security. Optional cookies for analytics, preferences, and advertising can be disabled using the toggles above.',
                ],
                [
                    'question' => 'How do I delete cookies already on my device?',
                    'answer' => 'You can clear cookies through your browser settings (see the browser controls section above) or by using the "Reject All Optional" button on this page.',
                ],
                [
                    'question' => 'Do you use third-party advertising cookies?',
                    'answer' => 'Only with your consent. Advertising cookies are disabled by default. When enabled, they help show more relevant ads based on your reading interests.',
                ],
                [
                    'question' => 'What\'s your cookie retention policy?',
                    'answer' => 'Session cookies expire when you close your browser. Persistent cookies are retained for up to 12 months unless you clear them sooner. Essential security tokens follow shorter rotation schedules.',
                ],
            ],
            'contact_heading' => 'Questions About Cookies?',
            'contact_description' => 'Our privacy team can help with any questions about how ZBC News uses tracking technologies.',
            'contact_email' => 'privacy@zbcnews.com',
            'banner_title' => 'We use cookies',
            'banner_description' => 'ZBC News uses essential cookies to keep the site working, and optional cookies for analytics, preferences, and advertising. Choose what you allow.',
        ];
    }
}
