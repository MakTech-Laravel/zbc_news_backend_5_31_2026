<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Enums\CommentStatus;
use App\Events\NewsPublished;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use App\Models\User;
use App\Support\BreakingTag;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ArticleService
{
    public function __construct(
        private readonly Article $article,
        private readonly SiteSettingsService $siteSettingsService,
        private readonly SeoMetaService $seoMetaService,
        private readonly StoredImageService $storedImageService,
        private readonly MediaService $mediaService,
    ) {}

    public function getAllArticles()
    {
        $this->publishDueScheduledArticles();

        return $this->articleQuery()
            ->latest()
            ->get();
    }

    public function getTrashedArticles()
    {
        return $this->articleQuery()
            ->onlyTrashed()
            ->latest('deleted_at')
            ->get();
    }

    public function getBySlug(string $slug): Article
    {
        $this->publishDueScheduledArticles();

        return $this->articleQuery()->where('slug', $slug)->firstOrFail();
    }

    /**
     * Publish any scheduled articles whose scheduled_publishing time is due (or past).
     */
    public function publishDueScheduledArticles(): int
    {
        $publishedCount = 0;

        $this->article
            ->newQuery()
            ->where('status', ArticleStatus::SCHEDULED->value)
            ->whereNotNull('scheduled_publishing')
            ->where('scheduled_publishing', '<=', now())
            ->orderBy('id')
            ->each(function (Article $article) use (&$publishedCount) {
                // Jobs must not bump updated_at — that is reserved for manual admin saves.
                $this->updateWithoutTouchingTimestamp($article, [
                    'status' => ArticleStatus::PUBLISHED->value,
                    'published_at' => $article->scheduled_publishing,
                ]);

                $article = $article->fresh([
                    'category',
                    'tags',
                    'user',
                ]);

                if (! $article) {
                    return;
                }

                DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
                $this->broadcastPublishedArticle($article);
                $publishedCount++;
            });

        return $publishedCount;
    }

    public function getPublishedBySlug(string $slug): Article
    {
        return $this->articleQuery()
            ->with(['user.userInformation'])
            ->where('slug', $slug)
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->firstOrFail();
    }

    public function getMostRead(bool $unique = false, int $limit = 10): Collection
    {
        $countExpr = $unique
            ? 'COUNT(DISTINCT COALESCE(ah.user_id, ah.ip_address)) as read_count'
            : 'COUNT(ah.id) as read_count';

        return $this->article
            ->select('articles.*')
            ->selectRaw($countExpr)
            ->join('article_histroys as ah', 'articles.id', '=', 'ah.article_id')
            ->where('ah.read_at', '>=', now()->subHours(24))
            ->where('articles.status', ArticleStatus::PUBLISHED->value)
            ->groupBy('articles.id')
            ->orderByDesc('read_count')
            ->with(['tags', 'category', 'user', 'media' => fn ($q) => $q->where('status', 'ready')
                ->whereIn('collection', ['featured', 'poster'])])
            ->withCount([
                'comments as comments_count' => fn ($q) => $q->where('status', CommentStatus::APPROVED),
            ])
            ->withSum('histroy', 'time_spent')
            ->limit($limit)
            ->get();
    }

    public function getLatestArticle(): Article
    {
        return $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->latest('published_at')
            ->firstOrFail();
    }

    public function getLatestStories(): Collection
    {
        return $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->latest('published_at')
            ->take(10)
            ->get();
    }

    public function getBreakingNewsArticles(int $limit = 10): Collection
    {
        $limit = min(max($limit, 1), 10);

        return $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereHas('tags', fn ($query) => $query->whereIn('tag', BreakingTag::VALUES))
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function getLatestArticleByTag(string $tagSlug, string $type = 'latest'): Collection
    {
        $query = $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereHas('tags', function ($q) use ($tagSlug) {
                $q->where('tag', $tagSlug);
            });

        return match ($type) {
            'trending' => $query->orderByDesc('views')->take(10)->get(),
            'recommended' => $query->withCount('saveArticles')->orderByDesc('save_articles_count')->take(10)->get(),
            default => $query->latest('published_at')->take(10)->get(),
        };
    }

    public function getLongReads(string $type = 'all', int $minMinutes = 5): Collection
    {
        $query = $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereHas('histroy', function ($q) use ($minMinutes) {
                $q->where('time_spent', '>=', $minMinutes * 60);
            });

        return match ($type) {
            'most-read' => $query->orderByDesc('views')->take(10)->get(),
            default => $query->latest('published_at')->take(10)->get(), // 'all'
        };
    }

    public function create(array $data, bool $isAutoSave = false): Article
    {
        return DB::transaction(function () use ($data, $isAutoSave) {
            $tags = $data['tags'] ?? [];
            unset($data['tags']);

            $categoryTitle = ArticleCategory::query()
                ->whereKey($data['article_category_id'] ?? null)
                ->value('title');

            $data = $this->seoMetaService->applyArticleMeta($data, $tags, $categoryTitle);

            $data['slug'] = $this->resolveSlug($data);
            $data['status'] = $this->resolveStatus($data);
            $data['published_at'] = $this->resolvePublishedAt($data);
            $data['featured_image'] = $this->resolveImage($data, 'featured_image', 'articles/featured-images');
            $data['open_graph_image'] = $this->resolveImage($data, 'open_graph_image', 'articles/og-images');
            $data['user_id'] = auth()->user()->id;

            $featuredMediaUuid = $this->pullMediaUuid($data, 'featured_media_uuid');
            $posterMediaUuid = $this->pullMediaUuid($data, 'poster_media_uuid');

            $article = $this->article->create($data);

            $this->syncArticleFeaturedMedia($article, $featuredMediaUuid, $posterMediaUuid, $data);

            if (! empty($tags)) {
                $tagIds = $this->resolveTags($tags);
                $article->tags()->sync($tagIds);
            }

            activity()
                ->performedOn($article)
                ->causedBy(auth()->user())
                ->withProperties([
                    'article_title' => $article->title,
                    'article_slug' => $article->slug,
                    'status' => $article->status,
                    'article_category_id' => $article->article_category_id,
                    'tags' => $tags,
                    'scheduled_publishing' => $article->scheduled_publishing,
                    'published_at' => $article->published_at,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->log($isAutoSave ? 'Article auto-saved' : 'Article created');

            $article = $article->load([
                'tags',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ]);

            if (! $isAutoSave && $article->status === ArticleStatus::PUBLISHED) {
                DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
                $this->broadcastPublishedArticle($article);
            }

            return $article->fresh([
                'tags',
                'category',
                'user',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ]);
        });
    }

    public function autoSave(?string $slug, array $data): Article
    {
        if (! $this->siteSettingsService->getOrDefault()->enable_auto_save) {
            abort(403, 'Auto-save is disabled.');
        }

        $data = $this->prepareAutoSaveData($data);

        if ($slug !== null) {
            return $this->update($slug, $data, isAutoSave: true);
        }

        $data['status'] = ArticleStatus::DRAFT->value;
        $data['article_category_id'] = $this->resolveAutoSaveCategoryId(
            $data['article_category_id'] ?? null,
        );

        return $this->create($data, isAutoSave: true);
    }

    public function update(string $slug, array $data, bool $isAutoSave = false): Article
    {
        $article = $this->article
            ->where('slug', $slug)
            ->firstOrFail();

        if ($isAutoSave) {
            $data = $this->applyAutoSaveStatusGuard($article, $data);
        }

        return DB::transaction(function () use ($article, $data, $isAutoSave) {
            $tags = $data['tags'] ?? null;
            unset($data['tags']);

            $tagNames = is_array($tags)
                ? $tags
                : $article->tags()->pluck('tag')->all();

            $categoryId = $data['article_category_id'] ?? $article->article_category_id;
            $categoryTitle = ArticleCategory::query()->whereKey($categoryId)->value('title');

            $data = $this->seoMetaService->applyArticleMeta($data, $tagNames, $categoryTitle);

            $data['slug'] = $this->resolveSlug($data, $article->id);
            $data['status'] = $this->resolveStatus($data);
            $data['published_at'] = $this->resolvePublishedAt($data, $article);
            $data['featured_image'] = $this->resolveImage($data, 'featured_image', 'articles/featured-images', $article);
            $data['open_graph_image'] = $this->resolveImage($data, 'open_graph_image', 'articles/og-images', $article);

            $featuredMediaUuid = $this->pullMediaUuid($data, 'featured_media_uuid');
            $posterMediaUuid = $this->pullMediaUuid($data, 'poster_media_uuid');

            $old = $article->only([
                'title',
                'slug',
                'status',
                'article_category_id',
                'scheduled_publishing',
                'published_at',
            ]);

            $previousStatus = $article->status;

            // Auto-save must not bump updated_at — only explicit admin Save does.
            if ($isAutoSave) {
                $this->updateWithoutTouchingTimestamp($article, $data);
            } else {
                $article->update($data);
            }

            $this->syncArticleFeaturedMedia($article, $featuredMediaUuid, $posterMediaUuid, $data);

            $contentChanged = $article->wasChanged(['title', 'article_description', 'excerpt', 'sub_title']);
            $becamePublished = $previousStatus !== ArticleStatus::PUBLISHED
                && $article->status === ArticleStatus::PUBLISHED;

            if (! is_null($tags)) {
                $tagIds = $this->resolveTags($tags);
                $article->tags()->sync($tagIds);
            }

            activity()
                ->performedOn($article)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => $old,
                    'new' => $article->fresh()->only(array_keys($old)),
                    'tags' => $tags,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->log($isAutoSave ? 'Article auto-saved' : 'Article updated');

            $article = $article->fresh([
                'tags',
                'category',
                'user',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ]);

            if (! $isAutoSave) {
                if ($becamePublished) {
                    DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
                    $this->broadcastPublishedArticle($article);
                } elseif ($article->status === ArticleStatus::PUBLISHED && $contentChanged) {
                    DispatchArticlePublishedNotifications::dispatch($article->id, 'updated');
                }
            }

            return $article;
        });
    }

    public function delete(string $slug): void
    {
        $article = $this->article->where('slug', $slug)->firstOrFail();

        activity()
            ->performedOn($article)
            ->causedBy(auth()->user())
            ->withProperties([
                'article_title' => $article->title,
                'article_slug' => $article->slug,
                'status' => $article->status,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Article deleted');

        $article->delete();
    }

    public function restore(string $slug): Article
    {
        $article = $this->article
            ->withTrashed()
            ->where('slug', $slug)
            ->firstOrFail();

        $article->restore();

        activity()
            ->performedOn($article)
            ->causedBy(auth()->user())
            ->withProperties([
                'article_title' => $article->title,
                'article_slug' => $article->slug,
                'status' => $article->status,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Article restored');

        return $article;
    }

    public function forceDelete(string $slug): void
    {
        $article = $this->article
            ->withTrashed()
            ->where('slug', $slug)
            ->firstOrFail();

        activity()
            ->performedOn($article)
            ->causedBy(auth()->user())
            ->withProperties([
                'article_title' => $article->title,
                'article_slug' => $article->slug,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Article permanently deleted');

        $this->storedImageService->delete($article->featured_image);
        $this->storedImageService->delete($article->open_graph_image);

        $article->forceDelete();
    }

    public function getByCategory(string $categorySlug, ?int $perPage = null, int $page = 1): array
    {
        $category = ArticleCategory::where('slug', $categorySlug)->firstOrFail();
        $perPage = $perPage ?? $this->siteSettingsService->getPostsPerPage();

        $query = $this->articleQuery()
            ->whereHas('category', function ($query) use ($categorySlug) {
                $query->where('slug', $categorySlug);
            })
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->latest('published_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));

        return [
            'category' => $category,
            'items' => $paginator->getCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @param  array{year?: int|null, month?: int|null, category?: string|null, author?: int|null}  $filters
     */
    public function getArchiveArticles(array $filters, ?int $perPage = null, int $page = 1): array
    {
        $perPage = $perPage ?? $this->siteSettingsService->getPostsPerPage();
        $query = $this->buildArchiveQuery($filters);

        $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));

        return [
            'items' => $paginator->getCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'year' => $filters['year'] ?? null,
                'month' => $filters['month'] ?? null,
                'category' => $filters['category'] ?? null,
                'author' => $filters['author'] ?? null,
            ],
        ];
    }

    public function getArchiveFilterOptions(?int $year = null): array
    {
        $baseQuery = fn () => $this->article
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereNotNull('published_at');

        $yearExpression = $this->publishedAtYearExpression();
        $monthExpression = $this->publishedAtMonthExpression();

        $years = (clone $baseQuery())
            ->selectRaw("{$yearExpression} as year, COUNT(*) as count")
            ->groupBy('year')
            ->orderByDesc('year')
            ->get()
            ->map(fn ($row) => [
                'year' => (int) $row->year,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $months = [];
        if ($year !== null) {
            $months = (clone $baseQuery())
                ->whereYear('published_at', $year)
                ->selectRaw("{$monthExpression} as month, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($row) => [
                    'month' => (int) $row->month,
                    'count' => (int) $row->count,
                ])
                ->values()
                ->all();
        }

        $categories = ArticleCategory::query()
            ->whereHas('articles', fn ($query) => $query
                ->where('status', ArticleStatus::PUBLISHED->value)
                ->whereNotNull('published_at'))
            ->orderBy('title')
            ->get(['id', 'title', 'slug'])
            ->map(fn (ArticleCategory $category) => [
                'id' => $category->id,
                'title' => $category->title,
                'slug' => $category->slug,
            ])
            ->values()
            ->all();

        $authors = User::query()
            ->select('users.id', 'users.name')
            ->whereHas('articles', fn ($query) => $query
                ->where('status', ArticleStatus::PUBLISHED->value)
                ->whereNotNull('published_at'))
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();

        return [
            'years' => $years,
            'months' => $months,
            'categories' => $categories,
            'authors' => $authors,
        ];
    }

    /**
     * @param  array{year?: int|null, month?: int|null, category?: string|null, author?: int|null}  $filters
     */
    private function buildArchiveQuery(array $filters)
    {
        $query = $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereNotNull('published_at');

        if (! empty($filters['year'])) {
            $query->whereYear('published_at', (int) $filters['year']);
        }

        if (! empty($filters['month'])) {
            $query->whereMonth('published_at', (int) $filters['month']);
        }

        if (! empty($filters['category'])) {
            $categorySlug = (string) $filters['category'];
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $categorySlug));
        }

        if (! empty($filters['author'])) {
            $query->where('user_id', (int) $filters['author']);
        }

        return $query->latest('published_at');
    }

    private function publishedAtYearExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', published_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(YEAR FROM published_at)::INTEGER',
            default => 'YEAR(published_at)',
        };
    }

    private function publishedAtMonthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%m', published_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(MONTH FROM published_at)::INTEGER',
            default => 'MONTH(published_at)',
        };
    }

    public function getRelatedArticles(string $slug, ?int $limit = null): Collection
    {
        $article = $this->getPublishedBySlug($slug);
        $limit = $limit ?? $this->siteSettingsService->getRelatedArticlesCount();

        if ($limit <= 0) {
            return new Collection;
        }

        $tagIds = $article->tags->pluck('id');

        return $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->where('id', '!=', $article->id)
            ->where(function ($query) use ($article, $tagIds) {
                $query->where('article_category_id', $article->article_category_id);
                if ($tagIds->isNotEmpty()) {
                    $query->orWhereHas('tags', function ($tagQuery) use ($tagIds) {
                        $tagQuery->whereIn('tags.id', $tagIds);
                    });
                }
            })
            ->latest('published_at')
            ->take($limit)
            ->get();
    }

    public function getActivities(string $slug)
    {
        $article = Article::where('slug', $slug)->firstOrFail();

        return Activity::query()
            ->where('subject_type', Article::class)
            ->where('subject_id', $article->id)
            ->with(['causer'])
            ->latest()
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'causer' => $activity->causer?->name,
                    'old' => $activity->properties['old'] ?? null,
                    'new' => $activity->properties['new'] ?? null,
                    'tags' => $activity->properties['tags'] ?? null,
                    'ip_address' => $activity->properties['ip_address'] ?? null,
                    'created_at' => $activity->created_at,
                ];
            });
    }

    // =========================================================================
    // Private Resolvers
    // =========================================================================

    private function articleQuery()
    {
        return $this->article
            ->with([
                'tags',
                'category',
                'user',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ])
            ->withCount([
                'comments as comments_count' => fn ($q) => $q->where('status', CommentStatus::APPROVED),
            ])
            ->withReadingTime();
    }

    /**
     * Pull optional media UUID keys out of the write payload so they are not
     * mass-assigned onto the articles table.
     *
     * @param  array<string, mixed>  $data
     */
    private function pullMediaUuid(array &$data, string $key): ?string
    {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        unset($data[$key]);

        if ($value === null || $value === '') {
            return '';
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Sync HasMedia featured/poster collections and keep featured_image in sync
     * for list/OG backward compatibility.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncArticleFeaturedMedia(
        Article $article,
        ?string $featuredMediaUuid,
        ?string $posterMediaUuid,
        array $data
    ): void {
        if ($featuredMediaUuid === '') {
            $this->mediaService->detachArticleCollection($article, 'featured');
            $this->mediaService->detachArticleCollection($article, 'poster');

            return;
        }

        if (is_string($featuredMediaUuid) && $featuredMediaUuid !== '') {
            $featured = $this->mediaService->attachToArticle($article, $featuredMediaUuid, 'featured');

            if ($featured && in_array($featured->media_type, ['video', 'audio'], true)) {
                if ($posterMediaUuid === '') {
                    $this->mediaService->detachArticleCollection($article, 'poster');
                } elseif (is_string($posterMediaUuid) && $posterMediaUuid !== '') {
                    $this->mediaService->attachToArticle($article, $posterMediaUuid, 'poster');
                }
            } else {
                $this->mediaService->detachArticleCollection($article, 'poster');
            }

            $article->unsetRelation('media');
            $poster = $article->posterMedia();
            $featured = $article->featuredMedia();

            $imageUrl = null;
            if ($featured?->isImage()) {
                $imageUrl = $featured->url;
            } elseif ($poster?->url) {
                $imageUrl = $poster->url;
            } elseif ($featured?->thumbnail_url) {
                $imageUrl = $featured->thumbnail_url;
            }

            if ($imageUrl && $imageUrl !== $article->featured_image) {
                // Side-effect sync must not change editorial updated_at.
                $this->updateWithoutTouchingTimestamp($article, ['featured_image' => $imageUrl]);
            }

            return;
        }

        // Legacy URL-only path: if featured_image cleared, detach media collections.
        if (array_key_exists('featured_image', $data) && ($data['featured_image'] === null || $data['featured_image'] === '')) {
            $this->mediaService->detachArticleCollection($article, 'featured');
            $this->mediaService->detachArticleCollection($article, 'poster');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareAutoSaveData(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $data['title'] = $title !== '' ? $title : 'Untitled';

        if (! array_key_exists('article_description', $data) || $data['article_description'] === null) {
            $data['article_description'] = '';
        }

        if (! array_key_exists('visibility', $data) || $data['visibility'] === null) {
            $data['visibility'] = ArticleVisibility::PUBLIC->value;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyAutoSaveStatusGuard(Article $article, array $data): array
    {
        unset($data['status']);

        $currentStatus = $article->status instanceof ArticleStatus
            ? $article->status->value
            : (string) $article->status;

        $data['status'] = $currentStatus;

        if ($currentStatus !== ArticleStatus::PUBLISHED->value) {
            unset($data['published_at']);
        }

        if ($currentStatus !== ArticleStatus::SCHEDULED->value) {
            unset($data['scheduled_publishing']);
        }

        return $data;
    }

    private function resolveAutoSaveCategoryId(?int $categoryId): int
    {
        if ($categoryId) {
            return $categoryId;
        }

        $defaultCategoryId = $this->siteSettingsService->getOrDefault()->default_category_id;
        if ($defaultCategoryId) {
            return (int) $defaultCategoryId;
        }

        $firstCategoryId = ArticleCategory::query()->orderBy('id')->value('id');
        if (! $firstCategoryId) {
            abort(422, 'A category is required before the article can be auto-saved.');
        }

        return (int) $firstCategoryId;
    }

    private function resolveSlug(array $data, ?int $excludeId = null): string
    {
        $base = Str::slug(! empty($data['slug']) ? $data['slug'] : $data['title']);
        $slug = $base;
        $count = 2;

        while (
            $this->article
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }

    private function resolveStatus(array $data): string
    {
        $status = $data['status'] ?? ArticleStatus::DRAFT->value;

        if ($status === ArticleStatus::SCHEDULED->value && empty($data['scheduled_publishing'])) {
            throw new \InvalidArgumentException('Scheduled publishing date is required for scheduled articles.');
        }

        // Past (or due) schedule → publish immediately instead of staying scheduled.
        if ($status === ArticleStatus::SCHEDULED->value) {
            $scheduledAt = Carbon::parse($data['scheduled_publishing']);
            if ($scheduledAt->lessThanOrEqualTo(now())) {
                return ArticleStatus::PUBLISHED->value;
            }
        }

        return $status;
    }

    private function resolvePublishedAt(array $data, ?Article $existing = null): ?Carbon
    {
        $status = $data['status'] ?? $existing?->status?->value ?? ArticleStatus::DRAFT->value;

        if ($status !== ArticleStatus::PUBLISHED->value) {
            return null;
        }

        if (! empty($data['published_at'])) {
            return Carbon::parse($data['published_at']);
        }

        // When a due/past schedule is promoted to published, keep that instant.
        if (! empty($data['scheduled_publishing'])) {
            $scheduledAt = Carbon::parse($data['scheduled_publishing']);
            if ($scheduledAt->lessThanOrEqualTo(now())) {
                return $scheduledAt;
            }
        }

        return $existing?->published_at ?? now();
    }

    private function resolveImage(
        array $data,
        string $field,
        string $folder,
        ?Article $existing = null
    ): ?string {
        if (! array_key_exists($field, $data)) {
            return $existing?->{$field} ?? null;
        }

        $value = $data[$field];
        $current = $existing?->{$field};

        if ($value instanceof UploadedFile) {
            $this->storedImageService->delete($current);

            return $this->storedImageService->upload($value, $folder);
        }

        if (is_string($value)) {
            $resolved = $this->storedImageService->resolveValue($value);

            if ($this->storedImageService->isDifferent($current, $resolved)) {
                $this->storedImageService->delete($current);

                return $resolved;
            }

            return $current;
        }

        if ($value === null) {
            $this->storedImageService->delete($current);

            return null;
        }

        return $current;
    }

    private function resolveTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tagName) => Tag::firstOrCreate(['tag' => strtolower(trim($tagName))])->id)
            ->toArray();
    }

    public function getGridArticles(int $limit = 50, array $excludeIds = []): Collection
    {
        return $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereNotIn('id', $excludeIds)
            ->orderByDesc('views')
            ->take($limit)
            ->get();
    }

    public function searchPublished(string $query, int $limit = 10): Collection
    {
        $term = trim($query);
        if ($term === '') {
            return collect();
        }

        $escaped = str_replace(['%', '_'], ['\%', '\_'], $term);
        $like = '%'.$escaped.'%';

        return $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('sub_title', 'like', $like)
                    ->orWhere('article_description', 'like', $like)
                    ->orWhere('meta_title', 'like', $like)
                    ->orWhere('meta_description', 'like', $like)
                    ->orWhereHas('tags', fn ($tagQuery) => $tagQuery->where('tag', 'like', $like))
                    ->orWhereHas('category', fn ($catQuery) => $catQuery->where('title', 'like', $like))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like));
            })
            ->orderByDesc('published_at')
            ->limit(min(max($limit, 1), 30))
            ->get();
    }

    public function broadcastPublishedArticle(Article $article): void
    {
        if ($article->status !== ArticleStatus::PUBLISHED) {
            return;
        }

        $article->loadMissing('category');

        event(new NewsPublished(
            articleId: $article->id,
            title: $article->title,
            slug: $article->slug,
            category: $article->category?->title ?? 'Uncategorized',
        ));
    }

    /**
     * Persist article attributes without changing updated_at.
     * Use for jobs, auto-save, and other non-manual side effects.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function updateWithoutTouchingTimestamp(Article $article, array $attributes): void
    {
        $article->timestamps = false;

        try {
            $article->update($attributes);
        } finally {
            $article->timestamps = true;
        }
    }

}
