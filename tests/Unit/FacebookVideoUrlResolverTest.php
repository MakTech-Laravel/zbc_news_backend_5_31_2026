<?php

namespace Tests\Unit;

use App\Support\FacebookVideoUrlResolver;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacebookVideoUrlResolverTest extends TestCase
{
    private FacebookVideoUrlResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FacebookVideoUrlResolver;
    }

    #[Test]
    public function it_normalizes_watch_urls(): void
    {
        $canonical = $this->resolver->normalizeCanonical('https://www.facebook.com/watch/?v=123456789');

        $this->assertSame('https://www.facebook.com/watch/?v=123456789', $canonical);
    }

    #[Test]
    public function it_rejects_non_facebook_hosts(): void
    {
        $this->assertFalse($this->resolver->isAllowedUrl('https://evil.example/video'));
        $this->assertNull($this->resolver->resolve('https://evil.example/video'));
    }

    #[Test]
    public function it_resolves_share_links_via_redirect(): void
    {
        Http::fake([
            'https://www.facebook.com/share/v/test123/' => Http::response('', 302, [
                'Location' => 'https://www.facebook.com/watch/?v=999888777',
            ]),
            'https://www.facebook.com/watch/*' => Http::response('ok', 200),
        ]);

        $canonical = $this->resolver->resolve('https://www.facebook.com/share/v/test123/');

        $this->assertSame('https://www.facebook.com/watch/?v=999888777', $canonical);
    }

    #[Test]
    public function it_detects_share_urls(): void
    {
        $this->assertTrue($this->resolver->isShareUrl('https://www.facebook.com/share/v/abc/'));
        $this->assertTrue($this->resolver->isShareUrl('https://www.facebook.com/share/r/abc/'));
        $this->assertFalse($this->resolver->isShareUrl('https://www.facebook.com/watch/?v=1'));
    }
}
