<?php

namespace Tests\Unit;

use App\Support\ArticleHtmlSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArticleHtmlSanitizerTest extends TestCase
{
    private ArticleHtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new ArticleHtmlSanitizer;
    }

    #[Test]
    public function it_keeps_youtube_embeds(): void
    {
        $html = '<div class="article-embed article-embed--youtube" data-embed-type="youtube">'
            .'<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="YouTube video"></iframe>'
            .'</div>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('article-embed--youtube', $result);
        $this->assertStringContainsString('https://www.youtube.com/embed/dQw4w9WgXcQ', $result);
    }

    #[Test]
    public function it_keeps_facebook_embeds(): void
    {
        $href = 'https://www.facebook.com/watch/?v=123456789';
        $src = 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($href);
        $html = '<div class="article-embed article-embed--facebook" data-embed-type="facebook">'
            .'<iframe src="'.$src.'" title="Facebook video"></iframe>'
            .'</div>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('article-embed--facebook', $result);
        $this->assertStringContainsString('/plugins/video.php', $result);
    }

    #[Test]
    public function it_keeps_library_video_tags(): void
    {
        $html = '<video src="https://cdn.example.test/media/clip.mp4" controls preload="metadata"></video>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<video', $result);
        $this->assertStringContainsString('clip.mp4', $result);
    }

    #[Test]
    public function it_strips_scripts_and_untrusted_iframes(): void
    {
        $html = '<p>Hello</p><script>alert(1)</script>'
            .'<iframe src="https://evil.example/embed"></iframe>'
            .'<iframe src="javascript:alert(1)"></iframe>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<p>Hello</p>', $result);
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('evil.example', $result);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    #[Test]
    public function it_strips_inline_event_handlers(): void
    {
        $html = '<p onclick="alert(1)">Click</p>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('Click', $result);
        $this->assertStringNotContainsString('onclick', $result);
    }
}
