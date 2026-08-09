<?php

namespace App\Support;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\Request;

class ArticleAuditLogger
{
    /**
     * Article columns captured in every audit snapshot.
     * `views` and `pending_editorial_timestamp` are intentionally excluded: they
     * change without editorial intent and would flood the log with noise.
     *
     * @var list<string>
     */
    private const TRACKED_FIELDS = [
        'title',
        'slug',
        'sub_title',
        'excerpt',
        'article_description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
        'visibility',
        'open_graph_image',
        'article_category_id',
        'is_breaking',
        'is_live',
        'is_live_blog',
        'live_video_url',
        'live_started_at',
        'live_ended_at',
        'scheduled_publishing',
        'published_at',
        'user_id',
    ];

    /** Rich-text columns stored as a size summary so activity rows stay small. */
    private const SUMMARISED_FIELDS = [
        'article_description',
    ];

    /** Columns holding an image path/URL — logged as a previewable file value. */
    private const IMAGE_FIELDS = [
        'open_graph_image',
    ];

    public static function log(
        Article $article,
        string $event,
        string $description,
        ?User $causer = null,
        array $properties = [],
    ): void {
        $logger = activity()
            ->performedOn($article)
            ->event($event)
            ->withProperties(array_merge([
                'article_title' => $article->title,
                'article_slug' => $article->slug,
                'status' => $article->status instanceof ArticleStatus
                    ? $article->status->value
                    : (string) $article->status,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ], $properties));

        if ($causer) {
            $logger->causedBy($causer);
        }

        $logger->log($description);
    }

    /**
     * Full editorial state of an article: columns, tags, featured/poster media
     * and document attachments. Taken before and after a save so every change
     * (including images and files) shows up in the audit log.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(Article $article): array
    {
        $values = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $values[$field] = self::normalizeValue($field, $article->getAttribute($field));
        }

        // Relation query methods (not loaded relations) so the snapshot is never stale.
        $values['tags'] = $article->tags()
            ->pluck('tag')
            ->map(fn ($tag) => (string) $tag)
            ->sort()
            ->values()
            ->all();

        // One row only: `featured_image` mirrors the featured media record, so tracking
        // both would log a single image swap twice.
        $values['featured_image'] = self::mediaFileValue($article->firstMedia('featured'))
            ?? self::imageFileValue($article->featured_image);

        $values['poster_media'] = self::mediaFileValue($article->firstMedia('poster'));

        $values['attachments'] = $article->attachments()
            ->with('media')
            ->get()
            ->map(fn ($attachment) => self::attachmentFileValue($article, $attachment))
            ->filter()
            ->values()
            ->all();

        return $values;
    }

    /**
     * Keep only the keys that actually changed, so each activity row stays small
     * and the UI table shows real edits instead of every column.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach (array_keys($before + $after) as $key) {
            $previous = $before[$key] ?? null;
            $current = $after[$key] ?? null;

            if (self::comparable($previous) === self::comparable($current)) {
                continue;
            }

            $old[$key] = $previous;
            $new[$key] = $current;
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Reduce a file value to a stable identity before comparing.
     * Attachments compare by name because their URL embeds the article slug — a
     * rename would otherwise flag every document as changed. Media compares by
     * URL because the display name differs between a legacy `featured_image`
     * string and the media record for the very same asset.
     */
    private static function comparable(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (isset($value['kind']) && array_key_exists('url', $value)) {
            $identity = $value['kind'] === 'file'
                ? ($value['name'] ?? null)
                : ($value['url'] ?? $value['name'] ?? null);

            return ['kind' => $value['kind'], 'identity' => $identity];
        }

        return array_map(static fn ($item) => self::comparable($item), $value);
    }

    /**
     * A file the UI can preview and download, instead of a raw storage path.
     *
     * @return array{kind: string, name: string, url: string|null, download_url: string|null}|null
     */
    private static function fileValue(
        string $kind,
        ?string $name,
        ?string $url,
        ?string $downloadUrl = null,
    ): ?array {
        $name = trim((string) $name);

        if ($name === '' && ($url === null || $url === '')) {
            return null;
        }

        return [
            'kind' => $kind,
            'name' => $name !== '' ? $name : 'Untitled',
            'url' => $url !== '' ? $url : null,
            'download_url' => $downloadUrl !== '' ? $downloadUrl : null,
        ];
    }

    private static function mediaFileValue($media): ?array
    {
        if (! $media) {
            return null;
        }

        $kind = match ($media->media_type) {
            'video' => 'video',
            'audio' => 'audio',
            'document' => 'file',
            default => 'image',
        };

        $preview = $kind === 'image'
            ? ($media->url ?? $media->thumbnail_url)
            : ($media->thumbnail_url ?? $media->url);

        return self::fileValue(
            $kind,
            $media->original_filename ?: (string) $media->uuid,
            MediaUrl::resolvePublic($preview),
            self::mediaDownloadPath($media->uuid),
        );
    }

    /**
     * Download links go through the API so the response carries
     * Content-Disposition — a cross-origin CDN link cannot force a download.
     * Relative path only; the frontend prefixes the public API origin.
     */
    private static function mediaDownloadPath(?string $uuid): ?string
    {
        return $uuid ? '/api/v1/media/'.$uuid.'/file?disposition=attachment' : null;
    }

    private static function attachmentFileValue(Article $article, $attachment): ?array
    {
        $media = $attachment->media;

        if (! $media) {
            return null;
        }

        $name = MediaUrl::downloadFilename(
            $media->original_filename,
            $media->extension ?: MediaUrl::extensionFromMime($media->mime_type),
            $attachment->label,
        );

        // Relative paths only — the frontend prefixes the public API origin.
        // Preview uses the article route (public), download uses the media route
        // so it also works while the article is still a draft.
        return self::fileValue(
            'file',
            $name,
            '/api/v1/articles/'.$article->slug.'/attachments/'.$media->uuid.'?disposition=inline',
            self::mediaDownloadPath($media->uuid),
        );
    }

    private static function imageFileValue(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $resolved = MediaUrl::resolvePublic($value);

        // Columns like open_graph_image store a bare URL. When that URL belongs to
        // a media record, reuse it so the download goes through the API proxy
        // instead of a CDN link the browser would just open.
        $media = Media::query()
            ->where('status', 'ready')
            ->where(fn ($query) => $query->where('url', $value)->orWhere('url', $resolved))
            ->first();

        if ($media) {
            return self::mediaFileValue($media);
        }

        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $name = basename((string) $path);
        $name = $name !== '' ? $name : 'Image';

        return self::fileValue(
            'image',
            $name,
            $resolved,
            MediaUrl::forceDownloadUrl($value, $name),
        );
    }

    private static function normalizeValue(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value;
        }

        if (in_array($field, self::IMAGE_FIELDS, true)) {
            return self::imageFileValue(is_string($value) ? $value : null);
        }

        if (in_array($field, self::SUMMARISED_FIELDS, true)) {
            return self::summariseRichText((string) $value);
        }

        if (is_scalar($value)) {
            return $value;
        }

        return null;
    }

    /** Rich text is compared by its readable length instead of storing full HTML. */
    private static function summariseRichText(string $html): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        $length = mb_strlen($text);

        if ($length === 0) {
            return 'Empty';
        }

        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);

        return number_format($length).' characters, '.number_format($words).' words';
    }

    /**
     * Map status transitions to discrete newsroom audit events.
     *
     * @return list<array{event: string, description: string}>
     */
    public static function statusTransitionEvents(?string $previous, string $next): array
    {
        $previous = $previous !== null ? strtolower($previous) : null;
        $next = strtolower($next);

        if ($previous === $next) {
            return [];
        }

        $events = [];

        if ($next === ArticleStatus::PENDING->value) {
            $events[] = [
                'event' => 'submitted_for_review',
                'description' => 'Article submitted for review',
            ];
        }

        if (
            $previous === ArticleStatus::PENDING->value
            && $next === ArticleStatus::PUBLISHED->value
        ) {
            $events[] = [
                'event' => 'approved',
                'description' => 'Article approved',
            ];
        }

        if (
            $previous === ArticleStatus::PENDING->value
            && in_array($next, [ArticleStatus::DRAFT->value, ArticleStatus::ARCHIVED->value], true)
        ) {
            $events[] = [
                'event' => 'rejected',
                'description' => 'Article rejected',
            ];
        }

        if ($next === ArticleStatus::PUBLISHED->value) {
            $events[] = [
                'event' => 'published',
                'description' => 'Article published',
            ];
        }

        if ($next === ArticleStatus::SCHEDULED->value) {
            $events[] = [
                'event' => 'scheduled',
                'description' => 'Article scheduled',
            ];
        }

        if ($next === ArticleStatus::ARCHIVED->value) {
            $events[] = [
                'event' => 'archived',
                'description' => 'Article archived',
            ];
        }

        return $events;
    }
}
