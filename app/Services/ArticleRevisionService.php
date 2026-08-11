<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleRevision;
use App\Models\Media;
use App\Models\User;
use App\Support\ArticleAuditLogger;
use App\Support\MediaUrl;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ArticleRevisionService
{
    /**
     * Article attributes stored in every restoreable revision.
     *
     * @var list<string>
     */
    private const ATTRIBUTE_FIELDS = [
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
        'featured_image',
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

    public function __construct(
        private readonly ArticleService $articleService,
    ) {}

    /**
     * Persist a restoreable full snapshot after a manual save.
     * Auto-saves are intentionally skipped so the revision list stays editorial.
     */
    public function record(Article $article, string $event = 'edited', ?User $causer = null): ?ArticleRevision
    {
        $article = $article->fresh([
            'tags',
            'category:id,title',
            'user:id,name',
            'breakingNewsItem',
            'attachments.media',
            'media' => fn ($q) => $q->where('status', 'ready')
                ->whereIn('collection', ['featured', 'poster']),
        ]) ?? $article;

        $snapshot = $this->buildSnapshot($article);
        $previous = ArticleRevision::query()
            ->where('article_id', $article->id)
            ->orderByDesc('version')
            ->first();

        $changes = $previous
            ? $this->diffSnapshots($previous->snapshot ?? [], $snapshot)
            : [
                'old' => [],
                'new' => $this->summariseSnapshot($snapshot),
                'kinds' => [],
                'diffs' => [],
            ];

        // Identical consecutive snapshots (no-op save) do not create a new revision.
        if ($previous && ($changes['old'] ?? []) === [] && ($changes['new'] ?? []) === []) {
            return null;
        }

        $version = (int) (ArticleRevision::query()
            ->where('article_id', $article->id)
            ->max('version') ?? 0) + 1;

        $status = $article->status instanceof ArticleStatus
            ? $article->status->value
            : (string) $article->status;

        return ArticleRevision::query()->create([
            'article_id' => $article->id,
            'version' => $version,
            'event' => $event,
            'title' => $article->title,
            'slug' => $article->slug,
            'status' => $status,
            'snapshot' => $snapshot,
            'changes' => $changes,
            'created_by' => $causer?->id ?? auth()->id(),
        ]);
    }

    /**
     * When featured/poster media metadata is edited in the media library,
     * create an article revision so History shows alt/caption/credit/copyright changes.
     *
     * @param  array{alt_text: ?string, caption: ?string, credit: ?string, copyright: ?string}  $before
     * @param  array{alt_text: ?string, caption: ?string, credit: ?string, copyright: ?string}  $after
     */
    public function recordMediaMetadataChange(
        Article $article,
        Media $media,
        array $before,
        array $after,
        ?User $causer = null,
    ): ?ArticleRevision {
        if (! in_array($media->collection, ['featured', 'poster'], true)) {
            return null;
        }

        if ($media->mediable_type !== Article::class || (int) $media->mediable_id !== (int) $article->id) {
            return null;
        }

        $prefix = $media->collection === 'poster' ? 'poster_' : 'featured_';
        $fieldMap = [
            'alt_text' => $prefix.'alt_text',
            'caption' => $prefix.'caption',
            'credit' => $prefix.'credit',
            'copyright' => $prefix.'copyright',
        ];

        $old = [];
        $new = [];
        $kinds = [];

        foreach ($fieldMap as $mediaKey => $flatKey) {
            $previous = $before[$mediaKey] ?? null;
            $current = $after[$mediaKey] ?? null;
            if ($this->valuesEqual($previous, $current)) {
                continue;
            }
            $old[$flatKey] = $previous;
            $new[$flatKey] = $current;
            $kinds[$flatKey] = $this->changeKind($previous, $current);
        }

        if ($old === [] && $new === []) {
            return null;
        }

        $article = $article->fresh([
            'tags',
            'category:id,title',
            'user:id,name',
            'breakingNewsItem',
            'attachments.media',
            'media' => fn ($q) => $q->where('status', 'ready')
                ->whereIn('collection', ['featured', 'poster']),
        ]) ?? $article;

        $snapshot = $this->buildSnapshot($article);

        $version = (int) (ArticleRevision::query()
            ->where('article_id', $article->id)
            ->max('version') ?? 0) + 1;

        $status = $article->status instanceof ArticleStatus
            ? $article->status->value
            : (string) $article->status;

        return ArticleRevision::query()->create([
            'article_id' => $article->id,
            'version' => $version,
            'event' => 'media_updated',
            'title' => $article->title,
            'slug' => $article->slug,
            'status' => $status,
            'snapshot' => $snapshot,
            'changes' => [
                'old' => $old,
                'new' => $new,
                'kinds' => $kinds,
                'diffs' => [],
            ],
            'created_by' => $causer?->id ?? auth()->id(),
        ]);
    }

    public function listForSlug(string $slug, int $perPage = 15): LengthAwarePaginator
    {
        $article = Article::withTrashed()->where('slug', $slug)->firstOrFail();
        $perPage = max(1, min($perPage, 100));

        $paginator = ArticleRevision::query()
            ->where('article_id', $article->id)
            ->with(['creator:id,name'])
            ->orderByDesc('version')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn (ArticleRevision $revision) => $this->mapListItem($revision));

        return $paginator;
    }

    public function findForSlug(string $slug, int $revisionId): array
    {
        $article = Article::withTrashed()->where('slug', $slug)->firstOrFail();
        $revision = ArticleRevision::query()
            ->where('article_id', $article->id)
            ->with(['creator:id,name'])
            ->whereKey($revisionId)
            ->firstOrFail();

        return $this->mapDetail($revision);
    }

    /**
     * Compare two revisions, or one revision against the live article.
     *
     * @return array{left: array<string, mixed>, right: array<string, mixed>, changes: array{old: array<string, mixed>, new: array<string, mixed>, kinds: array<string, string>, diffs: array<string, list<array{op: string, text: string}>>}}
     */
    public function compare(string $slug, ?int $leftId, ?int $rightId): array
    {
        $article = Article::withTrashed()->where('slug', $slug)->firstOrFail();

        $left = $leftId
            ? $this->revisionSnapshot($article->id, $leftId)
            : $this->buildSnapshot($article->fresh([
                'tags',
                'category:id,title',
                'user:id,name',
                'breakingNewsItem',
                'attachments.media',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ]) ?? $article);

        $right = $rightId
            ? $this->revisionSnapshot($article->id, $rightId)
            : $this->buildSnapshot($article->fresh([
                'tags',
                'category:id,title',
                'user:id,name',
                'breakingNewsItem',
                'attachments.media',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ]) ?? $article);

        $leftMeta = $leftId
            ? $this->revisionMeta($article->id, $leftId)
            : ['id' => null, 'version' => null, 'label' => 'Current version'];

        $rightMeta = $rightId
            ? $this->revisionMeta($article->id, $rightId)
            : ['id' => null, 'version' => null, 'label' => 'Current version'];

        return [
            'left' => $leftMeta + ['snapshot' => $this->summariseSnapshot($left)],
            'right' => $rightMeta + ['snapshot' => $this->summariseSnapshot($right)],
            'changes' => $this->diffSnapshots($left, $right),
        ];
    }

    public function restore(string $slug, int $revisionId, ?User $causer = null): Article
    {
        $article = Article::query()->where('slug', $slug)->firstOrFail();
        $revision = ArticleRevision::query()
            ->where('article_id', $article->id)
            ->whereKey($revisionId)
            ->firstOrFail();

        return DB::transaction(function () use ($article, $revision, $causer, $slug) {
            // Keep an undo point before overwriting live content.
            $this->record($article, 'pre_restore', $causer);

            $payload = $this->snapshotToUpdatePayload($revision->snapshot ?? []);
            // Always keep the live slug so restore does not orphan URLs or history links.
            $payload['slug'] = $slug;

            $updated = $this->articleService->update($slug, $payload, isAutoSave: false);

            // Re-apply frozen media metadata from the revision (alt/caption/credit).
            $this->restoreMediaMetadata($revision->snapshot ?? []);

            // update() already records an "edited" revision; mark intent in the audit log.
            ArticleAuditLogger::log(
                $updated->fresh([
                    'tags',
                    'category:id,title',
                    'user:id,name',
                    'breakingNewsItem',
                    'attachments.media',
                    'media' => fn ($q) => $q->where('status', 'ready')
                        ->whereIn('collection', ['featured', 'poster']),
                ]) ?? $updated,
                'revision_restored',
                'Article restored from revision #'.$revision->version,
                $causer,
                [
                    'old' => ['revision_id' => null, 'version' => null],
                    'new' => [
                        'revision_id' => $revision->id,
                        'version' => $revision->version,
                    ],
                ],
            );

            return $updated->fresh([
                'tags',
                'category',
                'user',
                'attachments.media',
                'media',
            ]) ?? $updated;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshot(Article $article): array
    {
        $article->loadMissing(['category:id,title', 'user:id,name']);

        $attributes = [];
        foreach (self::ATTRIBUTE_FIELDS as $field) {
            $attributes[$field] = $this->normalizeAttribute($article->getAttribute($field));
        }

        $featured = $article->firstMedia('featured');
        $poster = $article->firstMedia('poster');

        $attachments = $article->attachments()
            ->with('media')
            ->get()
            ->map(function ($attachment) {
                $uuid = $attachment->media?->uuid;
                if (! is_string($uuid) || $uuid === '') {
                    return null;
                }

                return [
                    'uuid' => $uuid,
                    'label' => $attachment->label,
                    'filename' => $attachment->media?->original_filename,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $breaking = null;
        $item = $article->breakingNewsItem;
        if ($item) {
            $breaking = [
                'enabled' => true,
                'priority' => $item->priority,
                'status' => $item->status instanceof BackedEnum ? $item->status->value : (string) $item->status,
                'starts_at' => $item->starts_at?->format('Y-m-d H:i:s'),
                'expires_at' => $item->expires_at?->format('Y-m-d H:i:s'),
                'headline_override' => $item->headline_override,
            ];
        } elseif ((bool) $article->is_breaking) {
            $breaking = ['enabled' => true];
        } else {
            $breaking = ['enabled' => false];
        }

        return [
            'attributes' => $attributes,
            'category_title' => $article->category?->title,
            'author_name' => $article->user?->name,
            'tags' => $article->tags()
                ->pluck('tag')
                ->map(fn ($tag) => (string) $tag)
                ->values()
                ->all(),
            'featured_media_uuid' => $featured?->uuid,
            'poster_media_uuid' => $poster?->uuid,
            // Freeze media metadata so compare/restore reflect this revision, not later edits.
            'featured_media' => $this->mediaMetaSnapshot($featured),
            'poster_media' => $this->mediaMetaSnapshot($poster),
            'attachments' => $attachments,
            'breaking' => $breaking,
        ];
    }

    /**
     * @return array{uuid: string, filename: string|null, url: string|null, alt_text: string|null, caption: string|null, credit: string|null, copyright: string|null}|null
     */
    private function mediaMetaSnapshot(?Media $media): ?array
    {
        if (! $media) {
            return null;
        }

        return [
            'uuid' => (string) $media->uuid,
            'filename' => $media->original_filename,
            'url' => MediaUrl::resolvePublic($media->url ?: $media->thumbnail_url),
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'credit' => $media->credit,
            'copyright' => $media->copyright,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function snapshotToUpdatePayload(array $snapshot): array
    {
        $attributes = is_array($snapshot['attributes'] ?? null) ? $snapshot['attributes'] : [];
        $payload = [];

        foreach (self::ATTRIBUTE_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $attributes[$field];
            }
        }

        // Caller may override slug; default to the snapshot slug for completeness.
        if (! array_key_exists('slug', $payload) && isset($attributes['slug'])) {
            $payload['slug'] = $attributes['slug'];
        }

        $payload['tags'] = is_array($snapshot['tags'] ?? null) ? $snapshot['tags'] : [];
        $payload['featured_media_uuid'] = $snapshot['featured_media_uuid'] ?? '';
        $payload['poster_media_uuid'] = $snapshot['poster_media_uuid'] ?? null;

        $attachments = [];
        foreach ($snapshot['attachments'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $uuid = $row['uuid'] ?? null;
            if (! is_string($uuid) || $uuid === '') {
                continue;
            }
            $attachments[] = [
                'uuid' => $uuid,
                'label' => is_string($row['label'] ?? null) ? $row['label'] : null,
            ];
        }
        $payload['attachments'] = $attachments;

        $breaking = is_array($snapshot['breaking'] ?? null) ? $snapshot['breaking'] : ['enabled' => false];
        $payload['is_breaking'] = (bool) ($breaking['enabled'] ?? false);
        if (array_key_exists('priority', $breaking)) {
            $payload['breaking_priority'] = $breaking['priority'];
        }
        if (array_key_exists('status', $breaking)) {
            $payload['breaking_status'] = $breaking['status'];
        }
        if (array_key_exists('starts_at', $breaking)) {
            $payload['breaking_starts_at'] = $breaking['starts_at'];
        }
        if (array_key_exists('expires_at', $breaking)) {
            $payload['breaking_expires_at'] = $breaking['expires_at'];
        }
        if (array_key_exists('headline_override', $breaking)) {
            $payload['breaking_headline'] = $breaking['headline_override'];
        }

        return $payload;
    }

    /**
     * Apply frozen alt/caption/credit from a revision onto the linked media rows.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreMediaMetadata(array $snapshot): void
    {
        foreach (['featured_media', 'poster_media'] as $key) {
            $meta = $snapshot[$key] ?? null;
            if (! is_array($meta)) {
                continue;
            }
            $uuid = $meta['uuid'] ?? null;
            if (! is_string($uuid) || $uuid === '') {
                continue;
            }

            $media = Media::query()->where('uuid', $uuid)->first();
            if (! $media) {
                continue;
            }

            $media->update([
                'alt_text' => array_key_exists('alt_text', $meta) ? $meta['alt_text'] : $media->alt_text,
                'caption' => array_key_exists('caption', $meta) ? $meta['caption'] : $media->caption,
                'credit' => array_key_exists('credit', $meta) ? $meta['credit'] : $media->credit,
                'copyright' => array_key_exists('copyright', $meta) ? $meta['copyright'] : $media->copyright,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{
     *   old: array<string, mixed>,
     *   new: array<string, mixed>,
     *   kinds: array<string, string>,
     *   diffs: array<string, list<array{op: string, text: string}>>
     * }
     */
    public function diffSnapshots(array $before, array $after): array
    {
        $left = $this->flattenSnapshot($before);
        $right = $this->flattenSnapshot($after);
        $old = [];
        $new = [];
        $kinds = [];
        $diffs = [];

        foreach (array_keys($left + $right) as $key) {
            $previous = $left[$key] ?? null;
            $current = $right[$key] ?? null;

            if ($this->valuesEqual($previous, $current)) {
                continue;
            }

            $kinds[$key] = $this->changeKind($previous, $current);

            if ($key === 'article_description') {
                $oldText = $this->plainText(is_string($previous) ? $previous : '');
                $newText = $this->plainText(is_string($current) ? $current : '');
                $old[$key] = $oldText !== '' ? $oldText : 'Empty';
                $new[$key] = $newText !== '' ? $newText : 'Empty';
                $diffs[$key] = $this->wordDiff($oldText, $newText);
                continue;
            }

            $old[$key] = $previous;
            $new[$key] = $current;
        }

        return [
            'old' => $old,
            'new' => $new,
            'kinds' => $kinds,
            'diffs' => $diffs,
        ];
    }

    private function changeKind(mixed $previous, mixed $current): string
    {
        $prevEmpty = $previous === null || $previous === '' || $previous === [] || $previous === 'Empty';
        $currEmpty = $current === null || $current === '' || $current === [] || $current === 'Empty';

        if ($prevEmpty && ! $currEmpty) {
            return 'added';
        }
        if (! $prevEmpty && $currEmpty) {
            return 'removed';
        }

        return 'modified';
    }

    private function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    }

    /**
     * Word-level diff for revision compare UI.
     *
     * @return list<array{op: string, text: string}>
     */
    private function wordDiff(string $old, string $new): array
    {
        $a = $old === '' ? [] : (preg_split('/\s+/u', $old) ?: []);
        $b = $new === '' ? [] : (preg_split('/\s+/u', $new) ?: []);

        $n = count($a);
        $m = count($b);
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                if ($a[$i] === $b[$j]) {
                    $lcs[$i][$j] = $lcs[$i + 1][$j + 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
            }
        }

        $segments = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $segments[] = ['op' => 'equal', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $segments[] = ['op' => 'delete', 'text' => $a[$i]];
                $i++;
            } else {
                $segments[] = ['op' => 'insert', 'text' => $b[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $segments[] = ['op' => 'delete', 'text' => $a[$i]];
            $i++;
        }
        while ($j < $m) {
            $segments[] = ['op' => 'insert', 'text' => $b[$j]];
            $j++;
        }

        // Merge adjacent same-op tokens for cleaner UI.
        $merged = [];
        foreach ($segments as $segment) {
            $last = $merged[count($merged) - 1] ?? null;
            if ($last && $last['op'] === $segment['op']) {
                $merged[count($merged) - 1]['text'] = $last['text'].' '.$segment['text'];
            } else {
                $merged[] = $segment;
            }
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function revisionSnapshot(int $articleId, int $revisionId): array
    {
        $revision = ArticleRevision::query()
            ->where('article_id', $articleId)
            ->whereKey($revisionId)
            ->firstOrFail();

        return is_array($revision->snapshot) ? $revision->snapshot : [];
    }

    /**
     * @return array{id: int, version: int, label: string, created_at: string|null, created_by: string|null}
     */
    private function revisionMeta(int $articleId, int $revisionId): array
    {
        $revision = ArticleRevision::query()
            ->where('article_id', $articleId)
            ->with(['creator:id,name'])
            ->whereKey($revisionId)
            ->firstOrFail();

        return [
            'id' => $revision->id,
            'version' => $revision->version,
            'label' => 'Version '.$revision->version,
            'created_at' => $revision->created_at?->toIso8601String(),
            'created_by' => $revision->creator?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapListItem(ArticleRevision $revision): array
    {
        $changes = is_array($revision->changes) ? $revision->changes : [];
        $changedFields = array_values(array_unique(array_merge(
            array_keys($changes['old'] ?? []),
            array_keys($changes['new'] ?? []),
        )));

        return [
            'id' => $revision->id,
            'version' => $revision->version,
            'event' => $revision->event,
            'title' => $revision->title,
            'slug' => $revision->slug,
            'status' => $revision->status,
            'changed_fields' => $changedFields,
            'created_by' => $revision->creator?->name ?? 'System',
            'created_at' => $revision->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDetail(ArticleRevision $revision): array
    {
        return $this->mapListItem($revision) + [
            'snapshot' => $this->summariseSnapshot(is_array($revision->snapshot) ? $revision->snapshot : []),
            'changes' => $revision->changes,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function summariseSnapshot(array $snapshot): array
    {
        $flat = $this->flattenSnapshot($snapshot);
        if (isset($flat['article_description']) && is_string($flat['article_description'])) {
            $flat['article_description'] = $this->summariseRichText($flat['article_description']);
        }

        return $flat;
    }

    /**
     * Flatten a restoreable snapshot into display values for compare/diff UI.
     * Image/document fields become previewable file objects (same shape as Activity Log),
     * while the stored snapshot itself keeps raw UUIDs/URLs for restore.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function flattenSnapshot(array $snapshot): array
    {
        $flat = [];
        $attributes = is_array($snapshot['attributes'] ?? null) ? $snapshot['attributes'] : [];

        foreach ($attributes as $key => $value) {
            // Replaced by readable category/author + media file objects below.
            if (in_array($key, [
                'featured_image',
                'open_graph_image',
                'article_category_id',
                'user_id',
            ], true)) {
                continue;
            }
            $flat[$key] = $value;
        }

        $flat['category'] = is_string($snapshot['category_title'] ?? null)
            ? $snapshot['category_title']
            : $this->resolveCategoryTitle($attributes['article_category_id'] ?? null);

        $flat['author'] = is_string($snapshot['author_name'] ?? null)
            ? $snapshot['author_name']
            : $this->resolveAuthorName($attributes['user_id'] ?? null);

        $featuredMeta = is_array($snapshot['featured_media'] ?? null) ? $snapshot['featured_media'] : null;
        $posterMeta = is_array($snapshot['poster_media'] ?? null) ? $snapshot['poster_media'] : null;

        $featuredMedia = $this->mediaByUuid(
            is_string($featuredMeta['uuid'] ?? null)
                ? $featuredMeta['uuid']
                : (is_string($snapshot['featured_media_uuid'] ?? null) ? $snapshot['featured_media_uuid'] : null),
        );
        $posterMedia = $this->mediaByUuid(
            is_string($posterMeta['uuid'] ?? null)
                ? $posterMeta['uuid']
                : (is_string($snapshot['poster_media_uuid'] ?? null) ? $snapshot['poster_media_uuid'] : null),
        );

        // Prefer frozen snapshot media (filename/url) so compare matches that revision.
        $flat['featured_image'] = $this->frozenMediaFileValue($featuredMeta, $featuredMedia)
            ?? $this->mediaFileValue($featuredMedia)
            ?? $this->imageFileValue(is_string($attributes['featured_image'] ?? null) ? $attributes['featured_image'] : null);

        $flat['featured_alt_text'] = is_array($featuredMeta) ? ($featuredMeta['alt_text'] ?? null) : null;
        $flat['featured_caption'] = is_array($featuredMeta) ? ($featuredMeta['caption'] ?? null) : null;
        $flat['featured_credit'] = is_array($featuredMeta) ? ($featuredMeta['credit'] ?? null) : null;
        $flat['featured_copyright'] = is_array($featuredMeta) ? ($featuredMeta['copyright'] ?? null) : null;

        $flat['open_graph_image'] = $this->imageFileValue(
            is_string($attributes['open_graph_image'] ?? null) ? $attributes['open_graph_image'] : null,
        );

        $flat['poster_media'] = $this->frozenMediaFileValue($posterMeta, $posterMedia)
            ?? $this->mediaFileValue($posterMedia);
        $flat['poster_alt_text'] = is_array($posterMeta) ? ($posterMeta['alt_text'] ?? null) : null;
        $flat['poster_caption'] = is_array($posterMeta) ? ($posterMeta['caption'] ?? null) : null;
        $flat['poster_credit'] = is_array($posterMeta) ? ($posterMeta['credit'] ?? null) : null;
        $flat['poster_copyright'] = is_array($posterMeta) ? ($posterMeta['copyright'] ?? null) : null;

        $flat['tags'] = is_array($snapshot['tags'] ?? null) ? array_values($snapshot['tags']) : [];

        $attachments = [];
        foreach ($snapshot['attachments'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $file = $this->attachmentFileValue($row);
            if ($file) {
                $attachments[] = $file;
            }
        }
        $flat['attachments'] = $attachments;

        $breaking = is_array($snapshot['breaking'] ?? null) ? $snapshot['breaking'] : [];
        $flat['is_breaking'] = (bool) ($breaking['enabled'] ?? $attributes['is_breaking'] ?? false);
        if ($flat['is_breaking']) {
            $flat['breaking_priority'] = $breaking['priority'] ?? null;
            $flat['breaking_status'] = $breaking['status'] ?? null;
            $flat['breaking_headline'] = $breaking['headline_override'] ?? null;
            $flat['breaking_starts_at'] = $breaking['starts_at'] ?? null;
            $flat['breaking_expires_at'] = $breaking['expires_at'] ?? null;
        }

        return $flat;
    }

    private function resolveCategoryTitle(mixed $categoryId): ?string
    {
        if (! is_numeric($categoryId)) {
            return null;
        }

        $title = ArticleCategory::query()->whereKey((int) $categoryId)->value('title');

        return is_string($title) ? $title : null;
    }

    private function resolveAuthorName(mixed $userId): ?string
    {
        if (! is_numeric($userId)) {
            return null;
        }

        $name = User::query()->whereKey((int) $userId)->value('name');

        return is_string($name) ? $name : null;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array{kind: string, name: string, url: string|null, download_url: string|null}|null
     */
    private function frozenMediaFileValue(?array $meta, ?Media $live): ?array
    {
        if ($meta === null) {
            return null;
        }

        $uuid = is_string($meta['uuid'] ?? null) ? $meta['uuid'] : null;
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $base = $this->mediaFileValue($live);
        $filename = is_string($meta['filename'] ?? null) && $meta['filename'] !== ''
            ? $meta['filename']
            : ($base['name'] ?? 'Untitled');
        $url = is_string($meta['url'] ?? null) && $meta['url'] !== ''
            ? MediaUrl::resolvePublic($meta['url'])
            : ($base['url'] ?? null);

        return $this->fileValue(
            $base['kind'] ?? 'image',
            $filename,
            $url,
            $base['download_url'] ?? $this->mediaDownloadPath($uuid),
        );
    }

    private function mediaByUuid(?string $uuid): ?Media
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return Media::query()
            ->where('uuid', $uuid)
            ->where('status', 'ready')
            ->first();
    }

    /**
     * @return array{kind: string, name: string, url: string|null, download_url: string|null}|null
     */
    private function fileValue(
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
            // Relative API path — frontend prefixes the public origin (works on live).
            'download_url' => $downloadUrl !== '' ? $downloadUrl : null,
        ];
    }

    private function mediaDownloadPath(?string $uuid): ?string
    {
        return $uuid ? '/api/v1/media/'.$uuid.'/file?disposition=attachment' : null;
    }

    private function mediaFileValue(?Media $media): ?array
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

        return $this->fileValue(
            $kind,
            $media->original_filename ?: (string) $media->uuid,
            MediaUrl::resolvePublic($preview),
            $this->mediaDownloadPath($media->uuid),
        );
    }

    private function imageFileValue(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $resolved = MediaUrl::resolvePublic($value);

        $media = Media::query()
            ->where('status', 'ready')
            ->where(fn ($query) => $query->where('url', $value)->orWhere('url', $resolved))
            ->first();

        if ($media) {
            return $this->mediaFileValue($media);
        }

        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $name = basename((string) $path);
        $name = $name !== '' ? $name : 'Image';

        return $this->fileValue(
            'image',
            $name,
            $resolved,
            MediaUrl::forceDownloadUrl($value, $name),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{kind: string, name: string, url: string|null, download_url: string|null}|null
     */
    private function attachmentFileValue(array $row): ?array
    {
        $uuid = $row['uuid'] ?? null;
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $media = $this->mediaByUuid($uuid);
        $label = is_string($row['label'] ?? null) ? $row['label'] : null;
        $filename = is_string($row['filename'] ?? null) ? $row['filename'] : null;

        if ($media) {
            $name = MediaUrl::downloadFilename(
                $media->original_filename,
                $media->extension ?: MediaUrl::extensionFromMime($media->mime_type),
                $label,
            );

            return $this->fileValue(
                'file',
                $name,
                MediaUrl::resolvePublic($media->url),
                $this->mediaDownloadPath($media->uuid),
            );
        }

        $name = $label ?: $filename ?: $uuid;

        return $this->fileValue('file', $name, null, $this->mediaDownloadPath($uuid));
    }

    private function isFileValue(mixed $value): bool
    {
        return is_array($value)
            && isset($value['kind'], $value['name'])
            && array_key_exists('url', $value);
    }

    private function fileIdentity(array $file): string
    {
        $kind = (string) ($file['kind'] ?? 'file');

        return $kind === 'file'
            ? (string) ($file['name'] ?? '')
            : (string) ($file['url'] ?? $file['name'] ?? '');
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        if ($this->isFileValue($a) || $this->isFileValue($b)) {
            if (! $this->isFileValue($a) || ! $this->isFileValue($b)) {
                return false;
            }

            return ($a['kind'] ?? null) === ($b['kind'] ?? null)
                && $this->fileIdentity($a) === $this->fileIdentity($b);
        }

        if (is_array($a) || is_array($b)) {
            $left = is_array($a) ? array_values($a) : [];
            $right = is_array($b) ? array_values($b) : [];

            if (count($left) !== count($right)) {
                return false;
            }

            foreach ($left as $index => $item) {
                if (! $this->valuesEqual($item, $right[$index])) {
                    return false;
                }
            }

            return true;
        }

        return $a === $b;
    }

    private function summariseRichText(string $html): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        $length = mb_strlen($text);

        if ($length === 0) {
            return 'Empty';
        }

        $words = count(preg_split('/\s+/u', $text) ?: []);

        return number_format($length).' characters, '.number_format($words).' words';
    }

    private function normalizeAttribute(mixed $value): mixed
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

        if (is_bool($value) || is_scalar($value)) {
            return $value;
        }

        return null;
    }
}
