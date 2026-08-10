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

    public function test_force_download_url_uses_short_safe_attachment_name(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/v1/folder/pic.jpg';

        $download = MediaUrl::forceDownloadUrl(
            $url,
            'Canada-Carney-Infantino-0_1786093292415_a00a6554-76ed-41ad-9284_1_.webp',
        );

        $this->assertNotNull($download);
        // Prefer CDN path extension (.jpg), never embed UUID/hyphen-heavy names.
        $this->assertStringContainsString('/upload/fl_attachment:file.jpg/', $download);
        $this->assertStringNotContainsString('Canada-Carney', $download);
        $this->assertStringNotContainsString('a00a6554-76ed', $download);
        $this->assertStringContainsString('folder/pic.jpg', $download);
    }

    public function test_force_download_url_bare_flag_when_no_extension(): void
    {
        $url = 'https://res.cloudinary.com/demo/raw/upload/v1/docs/file';

        $download = MediaUrl::forceDownloadUrl($url, '');

        $this->assertNotNull($download);
        $this->assertStringContainsString('/upload/fl_attachment/', $download);
    }

    public function test_is_remote_helper(): void
    {
        $this->assertTrue(
            MediaUrl::isRemote(
                'https://res.cloudinary.com/demo/image/upload/v1/folder/pic.jpg',
            ),
        );

        $this->assertFalse(MediaUrl::isRemote('/storage/local.jpg'));
    }
}
