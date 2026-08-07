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
}
