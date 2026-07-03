<?php

namespace Tests\Unit;

use App\Services\Newsletter\NewsletterContentFormatter;
use Tests\TestCase;

class NewsletterContentFormatterTest extends TestCase
{
    private NewsletterContentFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new NewsletterContentFormatter();
    }

    public function test_detects_html_from_rich_text_editor(): void
    {
        $this->assertTrue($this->formatter->appearsToBeHtml('<p>Hello <strong>world</strong></p>'));
    }

    public function test_treats_plain_text_as_non_html(): void
    {
        $this->assertFalse($this->formatter->appearsToBeHtml("Hello world\n\nSecond paragraph"));
    }

    public function test_plain_text_is_wrapped_in_paragraphs(): void
    {
        $result = $this->formatter->prepareBody("Line one\n\nLine two");

        $this->assertFalse($result['is_html']);
        $this->assertStringContainsString('<p style=', $result['html']);
        $this->assertStringContainsString('Line one', $result['html']);
        $this->assertStringContainsString('Line two', $result['html']);
    }

    public function test_html_content_is_preserved(): void
    {
        $input = '<h2>Title</h2><p>Body copy</p>';
        $result = $this->formatter->prepareBody($input);

        $this->assertTrue($result['is_html']);
        $this->assertSame($input, $result['html']);
    }
}
