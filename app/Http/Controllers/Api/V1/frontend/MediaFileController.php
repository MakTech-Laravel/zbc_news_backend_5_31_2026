<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\MediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Serves media downloads for activity log / revision history.
 *
 * Preferred path: proxy through Laravel with Content-Disposition (works cross-origin).
 * Fallback: redirect to Cloudinary fl_attachment when the origin cannot fetch the CDN
 * (common on local Windows SSL / blocked egress) — browser still downloads successfully.
 */
class MediaFileController extends Controller
{
    public function __invoke(Request $request, string $uuid): RedirectResponse|StreamedResponse
    {
        $disposition = $request->query('disposition') === 'inline' ? 'inline' : 'attachment';

        $media = Media::query()
            ->where('uuid', $uuid)
            ->where('status', 'ready')
            ->firstOrFail();

        $remoteUrl = MediaUrl::resolvePublic($media->url);

        abort_unless(is_string($remoteUrl) && $remoteUrl !== '', 404);

        $filename = MediaUrl::downloadFilename(
            $media->original_filename,
            $media->extension ?: MediaUrl::extensionFromMime($media->mime_type),
        );

        // If the origin cannot reach Cloudinary, send the browser straight to the CDN.
        if ($disposition === 'attachment' && MediaUrl::isRemote($remoteUrl) && str_contains($remoteUrl, '/upload/')) {
            $cdnDownload = MediaUrl::forceDownloadUrl($remoteUrl, $filename);
            if (is_string($cdnDownload) && $cdnDownload !== '') {
                $upstream = $this->fetchUpstream($remoteUrl);

                if ($upstream === null || ! $upstream->successful()) {
                    Log::warning('Media proxy upstream failed; redirecting to CDN download.', [
                        'uuid' => $uuid,
                        'status' => $upstream?->status(),
                    ]);

                    return redirect()->away($cdnDownload);
                }

                return $this->streamResponse($upstream, $disposition, $filename, $media->mime_type);
            }
        }

        $upstream = $this->fetchUpstream($remoteUrl);
        abort_unless($upstream !== null && $upstream->successful(), 502, 'Unable to fetch the file.');

        return $this->streamResponse($upstream, $disposition, $filename, $media->mime_type);
    }

    private function fetchUpstream(string $remoteUrl): ?\Illuminate\Http\Client\Response
    {
        try {
            return Http::withOptions([
                'stream' => true,
                'connect_timeout' => 15,
                'timeout' => 120,
                'read_timeout' => 120,
            ])->withHeaders([
                'Accept' => '*/*',
                'User-Agent' => 'ZBCNewsMediaProxy/1.0',
            ])->get($remoteUrl);
        } catch (Throwable $e) {
            Log::warning('Media proxy request threw.', [
                'url' => $remoteUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function streamResponse(
        \Illuminate\Http\Client\Response $upstream,
        string $disposition,
        string $filename,
        ?string $mimeType,
    ): StreamedResponse {
        $mime = $mimeType ?: 'application/octet-stream';
        if (strtolower((string) MediaUrl::extensionFromMime($mime)) === 'pdf'
            || str_ends_with(strtolower($filename), '.pdf')) {
            $mime = 'application/pdf';
        }

        $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?: 'download';
        $asciiName = str_replace(['"', '\\'], '_', $asciiName);

        return response()->stream(function () use ($upstream): void {
            $body = $upstream->toPsrResponse()->getBody();
            while (! $body->eof()) {
                echo $body->read(1024 * 64);
                if (function_exists('flush')) {
                    flush();
                }
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => sprintf(
                '%s; filename="%s"; filename*=UTF-8\'\'%s',
                $disposition,
                $asciiName,
                rawurlencode($filename),
            ),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
