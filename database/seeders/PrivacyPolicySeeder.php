<?php

namespace Database\Seeders;

use App\Models\PrivacyPolicySetting;
use Illuminate\Database\Seeder;

class PrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        PrivacyPolicySetting::query()->firstOrCreate([], self::defaultContent());
    }

    public static function defaultContent(): array
    {
        return [
            'hero_meta' => 'Last updated: June 1, 2026 · Version 4.1 · Effective: June 1, 2026',
            'plain_summary' => '<p>ZBC News collects information you provide and data about how you use our site. We use it to run the platform, personalise your experience, and deliver news. We don\'t sell your personal data. EU/UK/CA residents have specific data rights detailed below. The full policy below is legally binding.</p>',
            'overview' => <<<'HTML'
<p>ZBC News Media Group ("ZBC News," "we," "us," or "our") is committed to protecting your personal information. This Privacy Policy explains how we collect, use, share, and protect information when you use our website, mobile applications, newsletters, and any other services (collectively, "Services").</p>
<p>This policy applies to all ZBC News visitors, subscribers, contributors, and advertising partners. By using our Services, you accept the practices described in this policy.</p>
<p>We comply with the GDPR (EU/UK), CCPA (California), and LGPD (Brazil) as applicable to your jurisdiction.</p>
HTML,
            'data_we_collect' => <<<'HTML'
<p><strong>Information you give us:</strong></p>
<ul>
<li>Account registration: name, email, password, and subscription details</li>
<li>Payment information (processed via Stripe — we don't store card numbers)</li>
<li>Comments, letters, and editorial submissions</li>
<li>Correspondence with our editorial, support, or advertising teams</li>
<li>Newsletter subscriptions and reading preferences</li>
</ul>
<p><strong>Automatically collected:</strong></p>
<ul>
<li>Log data: IP address, browser, referring URL, pages visited, timestamps</li>
<li>Device data: screen size, OS, browser type, device identifiers</li>
<li>Reading behaviour: articles read, time on page, scroll depth, search queries</li>
<li>Cookie data — see our Cookie Policy for full details</li>
</ul>
HTML,
            'how_we_use' => <<<'HTML'
<p>We use your information for these purposes only:</p>
<ul>
<li>Delivering news content, newsletters, and notifications you've requested</li>
<li>Processing subscription payments and managing your account</li>
<li>Personalising article recommendations based on reading history</li>
<li>Displaying contextual and behavioural advertising (where consented)</li>
<li>Analysing usage to improve our editorial and technical products</li>
<li>Complying with legal obligations and protecting against fraud</li>
</ul>
<p><strong>We do not sell your personal data.</strong> We do not share personal data with third parties for their marketing purposes without explicit consent.</p>
HTML,
            'your_rights' => <<<'HTML'
<p>Depending on your jurisdiction, you have the following rights:</p>
<ul>
<li><strong>Access</strong> — Request a copy of the data we hold about you</li>
<li><strong>Rectification</strong> — Correct inaccurate or incomplete data</li>
<li><strong>Erasure</strong> — Request deletion of your personal data</li>
<li><strong>Portability</strong> — Receive your data in machine-readable format</li>
<li><strong>Objection</strong> — Object to processing based on legitimate interests</li>
<li><strong>Restrict Processing</strong> — Limit how we use your data</li>
</ul>
<p>To exercise any right, email <a href="mailto:privacy@zbcnews.com">privacy@zbcnews.com</a>. We will respond within 30 days (EU/UK: per GDPR Article 12).</p>
HTML,
            'data_security' => <<<'HTML'
<p>We implement appropriate technical and organisational measures:</p>
<ul>
<li>TLS 1.3 encryption for all data in transit</li>
<li>AES-256 encryption for sensitive data at rest</li>
<li>SOC 2 Type II certified infrastructure (AWS us-east-1)</li>
<li>Regular third-party penetration testing</li>
<li>Employee security training and role-based access controls</li>
<li>72-hour breach notification procedures</li>
</ul>
HTML,
            'third_parties' => <<<'HTML'
<p>We use these third-party processors:</p>
<table>
<thead>
<tr><th>Provider</th><th>Purpose</th><th>Location</th></tr>
</thead>
<tbody>
<tr><td>Stripe</td><td>Payment processing</td><td>US/EU</td></tr>
<tr><td>Amazon Web Services</td><td>Hosting &amp; storage</td><td>US</td></tr>
<tr><td>Plausible Analytics</td><td>Privacy-first analytics</td><td>EU</td></tr>
<tr><td>Postmark</td><td>Transactional email</td><td>US</td></tr>
<tr><td>Cloudflare</td><td>CDN &amp; DDoS protection</td><td>US/Global</td></tr>
</tbody>
</table>
HTML,
            'contact' => <<<'HTML'
<p>Our Data Protection Officer can be reached at:</p>
<p><strong>Email:</strong> <a href="mailto:privacy@zbcnews.com">privacy@zbcnews.com</a></p>
<p><strong>Mail:</strong><br>Data Protection Officer<br>ZBC News Media Group<br>250 West 57th St, New York, NY</p>
<p><a href="mailto:privacy@zbcnews.com">Contact Privacy Team</a> · <a href="/cookie-policy">Cookie Policy</a></p>
HTML,
        ];
    }
}
