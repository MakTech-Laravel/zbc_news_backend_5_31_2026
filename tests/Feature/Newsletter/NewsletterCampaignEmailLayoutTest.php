<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterCampaignEmailLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_email_uses_shared_layout_with_content_container(): void
    {
        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Weekly Update',
            'subject' => 'This week at ZBC',
            'preview_text' => 'Top stories from the newsroom',
            'content_html' => '<p>Breaking <strong>news</strong> inside.</p>',
            'status' => 'draft',
        ]);

        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'reader@example.com',
            'name' => 'Reader',
            'status' => 'verified',
            'verification_token' => 'verify-token',
            'unsubscribe_token' => 'unsub-token',
            'verified_at' => now(),
        ]);

        $html = app(NewsletterService::class)->buildEmailHtml($campaign, $subscriber);

        $this->assertStringContainsString('Weekly Update', $html);
        $this->assertStringContainsString('Top stories from the newsroom', $html);
        $this->assertStringContainsString('newsletter-content', $html);
        $this->assertStringContainsString('Breaking <strong>news</strong> inside.', $html);
        $this->assertStringContainsString('Manage preferences', $html);
        $this->assertStringContainsString('Unsubscribe', $html);
        $this->assertStringContainsString('Hello Reader', $html);
    }

    public function test_campaign_email_wraps_plain_text_content(): void
    {
        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Plain digest',
            'subject' => 'Plain digest',
            'content_html' => "First paragraph\n\nSecond paragraph",
            'status' => 'draft',
        ]);

        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'plain@example.com',
            'status' => 'verified',
            'verification_token' => 'verify-token-2',
            'unsubscribe_token' => 'unsub-token-2',
            'verified_at' => now(),
        ]);

        $html = app(NewsletterService::class)->buildEmailHtml($campaign, $subscriber);

        $this->assertStringContainsString('First paragraph', $html);
        $this->assertStringContainsString('Second paragraph', $html);
        $this->assertStringContainsString('newsletter-content', $html);
    }
}
