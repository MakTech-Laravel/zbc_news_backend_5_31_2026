<?php

namespace Database\Seeders;

use App\Models\TermsOfServiceSetting;
use Illuminate\Database\Seeder;

class TermsOfServiceSeeder extends Seeder
{
    public function run(): void
    {
        TermsOfServiceSetting::query()->firstOrCreate([], self::defaultContent());
    }

    public static function defaultContent(): array
    {
        return [
            'hero_meta' => 'Last updated: June 1, 2026 · Effective: June 1, 2026',
            'quick_summary' => <<<'HTML'
<ul>
<li>You must be 16+ to use ZBC News.</li>
<li>We do not tolerate harassment or misinformation.</li>
<li>Disputes are resolved via arbitration (NY law).</li>
<li>You own content you submit; you license it to us.</li>
<li>Subscriptions auto-renew; cancel anytime.</li>
<li>We may suspend accounts that violate these terms.</li>
</ul>
HTML,
            'account_terms' => <<<'HTML'
<p>To access certain features, you must create an account. By creating an account, you agree to:</p>
<ul>
<li>Provide accurate, complete, and up-to-date registration information</li>
<li>Maintain the security and confidentiality of your password</li>
<li>Be at least 16 years old (18 in some jurisdictions)</li>
<li>Notify us immediately of any unauthorised use at security@zbcnews.com</li>
<li>Hold only one personal account — accounts are non-transferable</li>
</ul>
<p>We may suspend or terminate accounts at our discretion for violation of these Terms, with or without notice. Serious violations (including fraud, abuse, or harassment) may result in immediate permanent suspension.</p>
HTML,
            'content_ip' => <<<'HTML'
<p><strong>Our Content:</strong> All ZBC News journalism, design, software, trademarks, and logos are protected by copyright and other intellectual property laws. You may share articles for non-commercial purposes with attribution; systematic copying or republication is prohibited.</p>
<p><strong>Your Content:</strong> When you submit comments, letters, or other content to ZBC News, you retain ownership but grant us a worldwide, royalty-free, perpetual licence to display, distribute, and adapt that content in connection with our Services.</p>
<p><strong>DMCA:</strong> If you believe content on ZBC News infringes your copyright, send a DMCA notice to <a href="mailto:dmca@zbcnews.com">dmca@zbcnews.com</a>.</p>
HTML,
            'subscriptions' => <<<'HTML'
<p><strong>Subscription Plans:</strong> ZBC News offers monthly and annual subscription tiers. Current pricing is displayed at checkout and is subject to change with 30 days' notice.</p>
<ul>
<li>Subscriptions auto-renew at the end of each billing period</li>
<li>Cancel anytime from account settings; access continues until period end</li>
<li>Annual subscribers receive a pro-rated refund if cancelled within 14 days</li>
<li>Payments processed by Stripe. We don't store card numbers.</li>
<li>Failed payments result in a 7-day grace period before access suspension</li>
</ul>
<p>To cancel or modify your subscription, visit Account Settings or email <a href="mailto:help@zbcnews.com">help@zbcnews.com</a>.</p>
HTML,
            'prohibited' => <<<'HTML'
<p>When using ZBC News, you must not:</p>
<ul>
<li>Post false, misleading, or defamatory information</li>
<li>Harass, threaten, or abuse other users or our staff</li>
<li>Scrape, crawl, or systematically download content without permission</li>
<li>Use ZBC News for political propaganda, undisclosed advertising, or coordinated influence operations</li>
<li>Circumvent paywalls, access controls, or technical restrictions</li>
<li>Impersonate ZBC News journalists or staff</li>
<li>Upload malware, viruses, or harmful code</li>
</ul>
HTML,
            'disputes' => <<<'HTML'
<p><strong>Governing Law:</strong> These Terms are governed by the laws of the State of New York, without regard to conflict-of-law principles.</p>
<p><strong>Arbitration:</strong> You agree that any dispute between you and ZBC News will be resolved through binding individual arbitration under the AAA Consumer Arbitration Rules, in New York, NY. Class action lawsuits are waived.</p>
<p><strong>Limitation of Liability:</strong> ZBC News's total liability to you for any claim arising under these Terms is limited to the amount you paid us in the 12 months preceding the claim. We're not liable for indirect, punitive, or consequential damages.</p>
<p><strong>Warranty Disclaimer:</strong> ZBC News provides Services "as is" without warranties of any kind, to the maximum extent permitted by law.</p>
HTML,
            'contact' => <<<'HTML'
<p><strong>Email:</strong> <a href="mailto:legal@zbcnews.com">legal@zbcnews.com</a></p>
<p><strong>Mail:</strong><br>Legal Department<br>ZBC News Media Group<br>250 West 57th St, New York, NY 10107</p>
<p><a href="mailto:legal@zbcnews.com">Contact Legal Team</a> · <a href="/privacy">Privacy Policy</a></p>
HTML,
        ];
    }
}
