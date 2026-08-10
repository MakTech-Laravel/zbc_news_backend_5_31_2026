<?php

namespace Tests\Unit;

use App\Support\MediaUrl;
use PHPUnit\Framework\TestCase;

class MediaUrlDownloadTest extends TestCase
{
    public function test_download_filename_keeps_uploaded_extension(): void
    {
        $this->assertSame(
            'report.pdf',
            MediaUrl::downloadFilename('report.pdf', 'pdf'),
        );

        $this->assertSame(
            'budget.xlsx',
            MediaUrl::downloadFilename('budget', 'xlsx'),
        );

        $this->assertSame(
            'notes.docx',
            MediaUrl::downloadFilename('notes.tmp', 'docx'),
        );
    }

    public function test_force_download_url_injects_cloudinary_attachment_flag(): void
    {
        $url = 'https://res.cloudinary.com/demo/raw/upload/v1/docs/file';

        $download = MediaUrl::forceDownloadUrl($url, 'briefing.pdf');

        $this->assertNotNull($download);
        $this->assertStringContainsString('/upload/fl_attachment:briefing.pdf/', $download);
        $this->assertStringContainsString('docs/file', $download);
    }

    public function test_force_download_url_strips_spaces_and_parens_that_break_cloudinary(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/v1/folder/pic.jpg';
        $messy = 'Canada-Carney-Infantino-0.1786093392415_a00a6554 (2).jpg';

        $download = MediaUrl::forceDownloadUrl($url, $messy);

        $this->assertNotNull($download);
        $this->assertStringNotContainsString('%20', $download);
        $this->assertStringNotContainsString('%28', $download);
        $this->assertStringNotContainsString('(', $download);
        $this->assertStringContainsString(
            'fl_attachment:Canada-Carney-Infantino-0.1786093392415_a00a6554_2_.jpg',
            $download,
        );
    }

    public function test_force_download_url_uses_bare_flag_when_name_empty(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/v1/folder/pic.jpg';

        $download = MediaUrl::forceDownloadUrl($url, '   ');

        $this->assertNotNull($download);
        $this->assertStringContainsString('/upload/fl_attachment/', $download);
        $this->assertStringNotContainsString('fl_attachment:', $download);
    }

    public function test_can_redirect_cdn_upload_urls(): void
    {
        // Kept for MediaUrl CDN helpers used by public article attachments.
        $this->assertTrue(
            MediaUrl::isRemote(
                'https://res.cloudinary.com/demo/image/upload/v1/folder/pic.jpg',
            ),
        );

        $this->assertFalse(MediaUrl::isRemote('/storage/local.jpg'));
    }
}
