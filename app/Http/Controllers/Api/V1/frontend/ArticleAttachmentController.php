<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleAttachment;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleAttachmentController extends Controller
{
    public function __invoke(Request $request, string $slug, string $uuid): StreamedResponse
    {
        $disposition = $request->query('disposition', 'inline') === 'attachment'
            ? 'attachment'
            : 'inline';

        $article = Article::query()
            ->where('slug', $slug)
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->firstOrFail();

        $attachment = ArticleAttachment::query()
            ->where('article_id', $article->id)
            ->whereHas('media', fn ($query) => $query
                ->where('uuid', $uuid)
                ->where('status', 'ready'))
            ->with('media')
            ->firstOrFail();

        $media = $attachment->media;
        $remoteUrl = MediaUrl::resolvePublic($media->url);

        abort_unless(is_string($remoteUrl) && $remoteUrl !== '', 404);

        $filename = MediaUrl::downloadFilename(
            $media->original_filename,
            $media->extension ?: MediaUrl::extensionFromMime($media->mime_type),
            $attachment->label,
        );

        $mime = $media->mime_type ?: 'application/octet-stream';
        $ext = strtolower((string) ($media->extension ?: MediaUrl::extensionFromMime($mime)));
        if ($ext === 'pdf') {
            $mime = 'application/pdf';
        }

        $upstream = Http::withOptions([
            'stream' => true,
            'timeout' => 120,
        ])->get($remoteUrl);

        abort_unless($upstream->successful(), 502, 'Unable to fetch the document.');

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
