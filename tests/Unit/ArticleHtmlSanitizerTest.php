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
    public function it_keeps_data_aspect_ratio_on_embeds(): void
    {
        $html = '<div class="article-embed article-embed--youtube" data-embed-type="youtube" '
            .'data-aspect-ratio="9 / 16" style="aspect-ratio:9 / 16">'
            .'<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="YouTube video"></iframe>'
            .'</div>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('data-aspect-ratio="9 / 16"', $result);
        $this->assertStringContainsString('aspect-ratio:9 / 16', $result);
    }

    #[Test]
    public function it_strips_editor_only_attributes(): void
    {
        $html = '<div class="article-embed article-embed--facebook" data-embed-type="facebook" '
            .'data-editor-video-replace-id="temp-id-123">'
            .'<iframe src="https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fwww.facebook.com%2Fwatch%2F%3Fv%3D123" '
            .'title="Facebook video"></iframe>'
            .'</div>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('data-editor-video-replace-id', $result);
        $this->assertStringContainsString('article-embed--facebook', $result);
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
