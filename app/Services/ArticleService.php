<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Enums\CommentStatus;
use App\Enums\LiveUpdateStatus;
use App\Events\NewsPublished;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;
use App\Models\ArticleAttachment;
use App\Models\ArticleCategory;
use App\Models\Media;
use App\Models\Tag;
use App\Models\User;
use App\Support\ArticleAuditLogger;
use App\Support\ArticleHtmlSanitizer;
use App\Support\BreakingTag;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        private readonly BreakingNewsService $breakingNewsService,
        private readonly SubMenuService $subMenuService,
        private readonly ArticleHtmlSanitizer $articleHtmlSanitizer,
    ) {}

    public function getAllArticles(bool $excludeLiveBlogs = false)
    {
        $this->publishDueScheduledArticles();

        $query = $this->articleQuery()->latest();

        if ($excludeLiveBlogs) {
            $query->where('is_live_blog', false);
        }

        return $query->get();
    }

    public function getLiveBlogArticles()
    {
        $this->publishDueScheduledArticles();

        return $this->articleQuery()
            ->with(['liveUpdates.user'])
            ->where('is_live_blog', true)
            ->latest()
            ->get();
    }

    /**
     * Public Live Updates feed: all published live-blog articles.
     * Ongoing (is_live) first, then ended/previous, with pagination.
     *
     * @return array{items: Collection<int, Article>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function getLiveBlogFeed(int $perPage = 12, int $page = 1): array
    {
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);

        $paginator = $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->where('is_live_blog', true)
            ->orderByDesc('is_live')
            ->orderByDesc('live_started_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function getTrashedArticles()
    {
        return $this->articleQuery()
            ->onlyTrashed()
            ->where('is_live_blog', false)
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

                ArticleAuditLogger::log(
                    $article,
                    'published',
                    'Article published (scheduled)',
                    null,
                    [
                        'old' => ['status' => ArticleStatus::SCHEDULED->value],
                        'new' => [
                            'status' => ArticleStatus::PUBLISHED->value,
                            'published_at' => optional($article->published_at)?->toIso8601String(),
                        ],
                        'source' => 'scheduler',
                    ],
                );

                DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
                $this->broadcastPublishedArticle($article);
                $publishedCount++;
            });

        return $publishedCount;
    }

    public function getPublishedBySlug(string $slug): Article
    {
        $article = $this->articleQuery()
            ->with(['user.userInformation'])
            ->where('slug', $slug)
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->firstOrFail();

        if ($article->is_live_blog) {
            $article->load([
                'liveUpdates' => fn ($q) => $q->published()->newestFirst()->with('user'),
            ]);
        }

        return $article;
    }

    /**
     * @return array{items: Collection, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function getMostRead(
        bool $unique = true,
        string $period = 'today',
        int $perPage = 5,
        int $page = 1,
    ): array {
        $perPage = max(1, min(20, $perPage));
        $page = max(1, $page);

        $countExpr = $unique
            ? 'COUNT(DISTINCT COALESCE(ah.user_id, ah.ip_address)) as read_count'
            : 'COUNT(ah.id) as read_count';

        $since = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'all' => null,
            default => now()->startOfDay(),
        };

        $baseQuery = $this->article
            ->newQuery()
            ->select('articles.id')
            ->selectRaw($countExpr)
            ->join('article_histroys as ah', 'articles.id', '=', 'ah.article_id')
            ->when($since !== null, fn ($q) => $q->where('ah.read_at', '>=', $since))
            ->where('articles.status', ArticleStatus::PUBLISHED->value)
            ->groupBy('articles.id');

        $total = (int) DB::query()
            ->fromSub((clone $baseQuery)->toBase(), 'most_read_ranked')
            ->count();

        $rankedIds = (clone $baseQuery)
            ->orderByDesc('read_count')
            ->forPage($page, $perPage)
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->id => (int) $row->read_count]);

        $items = $rankedIds->isEmpty()
            ? new Collection()
            : $this->article
                ->newQuery()
                ->whereIn('articles.id', $rankedIds->keys())
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
                ->withSum('histroy', 'time_spent')
                ->get()
                ->map(function (Article $article) use ($rankedIds) {
                    $article->setAttribute('read_count', (int) $rankedIds[$article->id]);

                    return $article;
                })
                ->sortByDesc(fn (Article $article) => (int) $article->read_count)
                ->values();

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    public function getLatestArticle(): Article
    {
        $this->publishDueScheduledArticles();

        return $this->publishedHomepageFeedQuery()->firstOrFail();
    }

    public function getLatestStories(): Collection
    {
        $this->publishDueScheduledArticles();

        return $this->publishedHomepageFeedQuery()->limit(10)->get();
    }

    public function getBreakingNewsArticles(int $limit = 10): Collection
    {
        return $this->breakingNewsService
            ->listForTicker($limit)
            ->map(fn ($item) => $item->article)
            ->filter()
            ->values();
    }

    public function getAdminBreakingNewsArticles(): Collection
    {
        return $this->breakingNewsService->listForAdmin();
    }

    public function clearBreakingNews(string $slug): Article
    {
        $article = $this->article->where('slug', $slug)->firstOrFail();
        $this->breakingNewsService->removeForArticle($article);

        return $article->fresh([
            'tags',
            'category',
            'user',
            'breakingNewsItem',
            'media' => fn ($q) => $q->where('status', 'ready')
                ->whereIn('collection', ['featured', 'poster']),
        ]);
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

            $breakingPayload = $this->extractBreakingPayload($data);

            $data['is_breaking'] = $breakingPayload !== null
                ? (bool) ($breakingPayload['enabled'] ?? false)
                : false;
            $tags = $this->syncBreakingTagWithFlag($tags, $data['is_breaking']);

            $data['is_live_blog'] = $this->resolveIsLiveBlog($data);

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

            if (! array_key_exists('article_description', $data) || $data['article_description'] === null) {
                $data['article_description'] = '';
            }

            $data = $this->sanitizeRichTextFields($data);

            $featuredMediaUuid = $this->pullMediaUuid($data, 'featured_media_uuid');
            $posterMediaUuid = $this->pullMediaUuid($data, 'poster_media_uuid');
            $attachmentsPayload = $this->pullAttachmentsPayload($data);

            $article = $this->article->create($data);

            $this->syncArticleFeaturedMedia(
                $article,
                $featuredMediaUuid,
                $posterMediaUuid,
                $data,
                preserveTimestamp: true,
            );

            if ($attachmentsPayload !== null) {
                $this->syncArticleAttachments($article, $attachmentsPayload);
            }

            if (! empty($tags)) {
                $tagIds = $this->resolveTags($tags);
                $article->tags()->sync($tagIds);
            }

            // Create must not surface an "Updated" time until a later editorial save.
            $article->timestamps = false;
            try {
                $article->forceFill([
                    'updated_at' => $article->created_at,
                    'pending_editorial_timestamp' => false,
                ])->save();
            } finally {
                $article->timestamps = true;
            }

            if ($breakingPayload !== null) {
                $this->breakingNewsService->syncForArticle(
                    $article->fresh(),
                    $breakingPayload,
                    auth()->id(),
                );
            }

            $causer = auth()->user();
            $createdSnapshot = ArticleAuditLogger::snapshot($article->fresh());

            ArticleAuditLogger::log(
                $article,
                $isAutoSave ? 'auto_saved' : 'created',
                $isAutoSave ? 'Article auto-saved' : 'Article created',
                $causer instanceof User ? $causer : null,
                [
                    'old' => [],
                    'new' => $createdSnapshot,
                    'tags' => $createdSnapshot['tags'] ?? $tags,
                ],
            );

            $statusValue = $article->status instanceof ArticleStatus
                ? $article->status->value
                : (string) $article->status;

            if (! $isAutoSave) {
                foreach (ArticleAuditLogger::statusTransitionEvents(null, $statusValue) as $transition) {
                    // Skip duplicate "published" when create already implies draft-only create.
                    if ($transition['event'] === 'published' || $transition['event'] === 'scheduled'
                        || $transition['event'] === 'submitted_for_review'
                        || $transition['event'] === 'archived') {
                        ArticleAuditLogger::log(
                            $article,
                            $transition['event'],
                            $transition['description'],
                            $causer instanceof User ? $causer : null,
                            [
                                'old' => ['status' => null],
                                'new' => ['status' => $statusValue],
                            ],
                        );
                    }
                }
            }

            $article = $article->load([
                'tags',
                'breakingNewsItem',
                'attachments.media',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ]);

            if (! $isAutoSave) {
                app(ArticleRevisionService::class)->record(
                    $article,
                    'created',
                    $causer instanceof User ? $causer : null,
                );
            }

            if (! $isAutoSave && $article->status === ArticleStatus::PUBLISHED) {
                DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
                $this->broadcastPublishedArticle($article);
            }

            return $article->fresh([
                'tags',
                'category',
                'user',
                'breakingNewsItem',
                'attachments.media',
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
            // Never accept this from the client — set only by auto-save / manual-save flow.
            unset($data['pending_editorial_timestamp']);

            $data = $this->sanitizeRichTextFields($data);

            $tagsProvided = array_key_exists('tags', $data);
            $tags = $data['tags'] ?? null;
            unset($data['tags']);

            $breakingPayload = $this->extractBreakingPayload($data);

            $tagNames = is_array($tags)
                ? $tags
                : $article->tags()->pluck('tag')->all();

            if ($breakingPayload !== null) {
                $data['is_breaking'] = (bool) ($breakingPayload['enabled'] ?? false);
            } elseif (array_key_exists('is_breaking', $data)) {
                $data['is_breaking'] = $this->resolveIsBreaking($data);
                $breakingPayload = ['enabled' => $data['is_breaking']];
            } else {
                $data['is_breaking'] = (bool) $article->is_breaking;
            }

            if (array_key_exists('is_live_blog', $data)) {
                $data['is_live_blog'] = $this->resolveIsLiveBlog($data);
            } else {
                $data['is_live_blog'] = (bool) $article->is_live_blog;
            }

            $tagNames = $this->syncBreakingTagWithFlag($tagNames, (bool) $data['is_breaking']);
            $tags = is_array($tags) ? $tagNames : $tags;

            $categoryId = $data['article_category_id'] ?? $article->article_category_id;
            $categoryTitle = ArticleCategory::query()->whereKey($categoryId)->value('title');

            // Keys present before SEO autofill — meta_* filled by SeoMetaService must not
            // alone count as an editorial bump.
            $requestKeys = array_keys($data);

            $data = $this->seoMetaService->applyArticleMeta($data, $tagNames, $categoryTitle);

            $data['slug'] = $this->resolveSlug($data, $article->id);
            $data['status'] = $this->resolveStatus($data);
            $data['published_at'] = $this->resolvePublishedAt($data, $article);
            $data['featured_image'] = $this->resolveImage($data, 'featured_image', 'articles/featured-images', $article);
            $data['open_graph_image'] = $this->resolveImage($data, 'open_graph_image', 'articles/og-images', $article);

            $featuredMediaUuid = $this->pullMediaUuid($data, 'featured_media_uuid');
            $posterMediaUuid = $this->pullMediaUuid($data, 'poster_media_uuid');
            $attachmentsPayload = $this->pullAttachmentsPayload($data);

            // Captured before any write so image/file/tag changes are diffable.
            $beforeSnapshot = ArticleAuditLogger::snapshot($article);

            $previousStatus = $article->status instanceof ArticleStatus
                ? $article->status->value
                : (string) $article->status;

            $editorialChanged = $this->manualUpdateTouchesEditorialTimestamp(
                $article,
                $data,
                $featuredMediaUuid,
                $posterMediaUuid,
                $tagsProvided ? $tagNames : null,
                $requestKeys,
            );

            // Auto-save may already have written editorial content without bumping updated_at.
            // Manual Save must still bump when that pending flag is set.
            $shouldBumpUpdatedAt = ! $isAutoSave
                && ($editorialChanged || (bool) $article->pending_editorial_timestamp);

            // Never bump during attribute write; touch once after media/tags when editorial.
            $this->updateWithoutTouchingTimestamp($article, $data);
            $contentChanged = $article->wasChanged(['title', 'article_description', 'excerpt', 'sub_title']);
            $becamePublished = $previousStatus !== ArticleStatus::PUBLISHED->value
                && $article->status === ArticleStatus::PUBLISHED;

            $this->syncArticleFeaturedMedia($article, $featuredMediaUuid, $posterMediaUuid, $data, true);

            if ($attachmentsPayload !== null) {
                $this->syncArticleAttachments($article, $attachmentsPayload);
            }

            // Always sync tags when is_breaking may have changed the tag set.
            $tagIds = $this->resolveTags($tagNames);
            $article->tags()->sync($tagIds);

            if ($isAutoSave && $editorialChanged) {
                $this->updateWithoutTouchingTimestamp($article, [
                    'pending_editorial_timestamp' => true,
                ]);
            }

            if ($shouldBumpUpdatedAt) {
                $article->touch();
            }

            if (! $isAutoSave && (bool) $article->pending_editorial_timestamp) {
                $this->updateWithoutTouchingTimestamp($article, [
                    'pending_editorial_timestamp' => false,
                ]);
            }

            if ($breakingPayload !== null) {
                $this->breakingNewsService->syncForArticle(
                    $article->fresh(),
                    $breakingPayload,
                    auth()->id(),
                );
            }

            $fresh = $article->fresh();
            $newStatus = $fresh->status instanceof ArticleStatus
                ? $fresh->status->value
                : (string) $fresh->status;
            $causer = auth()->user();
            $causerUser = $causer instanceof User ? $causer : null;
            $afterSnapshot = ArticleAuditLogger::snapshot($fresh);
            $diffProperties = ArticleAuditLogger::diff($beforeSnapshot, $afterSnapshot) + [
                'tags' => $afterSnapshot['tags'] ?? $tagNames,
            ];

            if ($isAutoSave) {
                ArticleAuditLogger::log(
                    $fresh,
                    'auto_saved',
                    'Article auto-saved',
                    $causerUser,
                    $diffProperties,
                );
            } else {
                $statusEvents = ArticleAuditLogger::statusTransitionEvents($previousStatus, $newStatus);

                if ($statusEvents === []) {
                    ArticleAuditLogger::log(
                        $fresh,
                        'edited',
                        'Article edited',
                        $causerUser,
                        $diffProperties,
                    );
                } else {
                    foreach ($statusEvents as $transition) {
                        ArticleAuditLogger::log(
                            $fresh,
                            $transition['event'],
                            $transition['description'],
                            $causerUser,
                            $diffProperties,
                        );
                    }
                }
            }

            $article = $article->fresh([
                'tags',
                'category',
                'user',
                'breakingNewsItem',
                'attachments.media',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ]);

            if (! $isAutoSave) {
                app(ArticleRevisionService::class)->record($article, 'edited', $causerUser);
            }

            if (! $isAutoSave) {
                if ($becamePublished) {
                    DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
                    $this->broadcastPublishedArticle($article);
                } elseif ($article->status === ArticleStatus::PUBLISHED && $contentChanged) {
                    DispatchArticlePublishedNotifications::dispatch($article->id, 'updated');
                }

                if (
                    $article->status === ArticleStatus::PUBLISHED
                    && ($shouldBumpUpdatedAt || $becamePublished)
                ) {
                    $this->subMenuService->flushPublicCache();
                }
            }

            return $article;
        });
    }

    public function delete(string $slug): void
    {
        $article = $this->article->where('slug', $slug)->firstOrFail();

        ArticleAuditLogger::log(
            $article,
            'deleted',
            'Article deleted',
            auth()->user() instanceof User ? auth()->user() : null,
        );

        $article->delete();
    }

    public function restore(string $slug): Article
    {
        $article = $this->article
            ->withTrashed()
            ->where('slug', $slug)
            ->firstOrFail();

        $article->restore();

        ArticleAuditLogger::log(
            $article,
            'restored',
            'Article restored',
            auth()->user() instanceof User ? auth()->user() : null,
        );

        return $article;
    }

    public function forceDelete(string $slug): void
    {
        $article = $this->article
            ->withTrashed()
            ->where('slug', $slug)
            ->firstOrFail();

        ArticleAuditLogger::log(
            $article,
            'permanently_deleted',
            'Article permanently deleted',
            auth()->user() instanceof User ? auth()->user() : null,
        );

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

    /**
     * Paginated audit trail for one article.
     * `withTrashed` so a soft-deleted article still exposes its history.
     */
    public function getActivities(string $slug, int $perPage = 15): LengthAwarePaginator
    {
        $article = Article::withTrashed()->where('slug', $slug)->firstOrFail();

        $perPage = max(1, min($perPage, 100));

        $paginator = Activity::query()
            ->where('subject_type', Article::class)
            ->where('subject_id', $article->id)
            ->with(['causer'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function ($activity) {
            $properties = $activity->properties ?? collect();

            return [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'causer' => $activity->causer?->name ?? 'System',
                'causer_id' => $activity->causer_id,
                'old' => $properties['old'] ?? null,
                'new' => $properties['new'] ?? null,
                'tags' => $properties['tags'] ?? null,
                'status' => $properties['status'] ?? null,
                'ip_address' => $properties['ip_address'] ?? null,
                'user_agent' => $properties['user_agent'] ?? null,
                'created_at' => $activity->created_at,
            ];
        });

        return $paginator;
    }

    public function findAnyBySlug(string $slug): Article
    {
        return Article::withTrashed()->where('slug', $slug)->firstOrFail();
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
                'breakingNewsItem',
                'attachments.media',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ])
            ->withCount([
                'comments as comments_count' => fn ($q) => $q->where('status', CommentStatus::APPROVED),
            ])
            ->withReadingTime();
    }

    /**
     * Published articles for the homepage news stream.
     * Active live blogs sort by their latest published timeline entry; others by publish time.
     */
    private function publishedHomepageFeedQuery()
    {
        return $this->articleQuery()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->orderByRaw($this->homepageFeedSortSql().' DESC')
            ->orderByDesc('articles.id');
    }

    /**
     * SQL expression for unified homepage feed ordering (no extra DB columns).
     */
    private function homepageFeedSortSql(): string
    {
        $published = LiveUpdateStatus::PUBLISHED->value;

        return <<<SQL
CASE
    WHEN articles.is_live_blog = 1 AND articles.is_live = 1 THEN COALESCE(
        (
            SELECT MAX(alu.posted_at)
            FROM article_live_updates AS alu
            WHERE alu.article_id = articles.id
              AND alu.status = '{$published}'
              AND alu.deleted_at IS NULL
        ),
        articles.published_at,
        articles.created_at
    )
    ELSE COALESCE(articles.published_at, articles.created_at)
END
SQL;
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
     * @param  array<string, mixed>  $data
     * @return list<array{uuid: string, label?: string|null}>|null
     */
    private function pullAttachmentsPayload(array &$data): ?array
    {
        if (! array_key_exists('attachments', $data)) {
            return null;
        }

        $raw = $data['attachments'];
        unset($data['attachments']);

        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (is_string($row) && $row !== '') {
                $items[] = ['uuid' => $row, 'label' => null];
                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $uuid = $row['uuid'] ?? $row['media_uuid'] ?? null;
            if (! is_string($uuid) || $uuid === '') {
                continue;
            }

            $label = $row['label'] ?? null;
            $items[] = [
                'uuid' => $uuid,
                'label' => is_string($label) ? trim($label) : null,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{uuid: string, label?: string|null}>  $items
     */
    private function syncArticleAttachments(Article $article, array $items): void
    {
        $keepIds = [];

        foreach (array_values($items) as $index => $item) {
            $media = Media::query()
                ->where('uuid', $item['uuid'])
                ->where('status', 'ready')
                ->where('media_type', 'document')
                ->first();

            if (! $media) {
                continue;
            }

            $attachment = ArticleAttachment::query()->updateOrCreate(
                [
                    'article_id' => $article->id,
                    'media_id' => $media->id,
                ],
                [
                    'label' => $item['label'] !== null && $item['label'] !== ''
                        ? $item['label']
                        : $media->original_filename,
                    'sort_order' => $index,
                ],
            );

            $keepIds[] = $attachment->id;
        }

        ArticleAttachment::query()
            ->where('article_id', $article->id)
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->delete();
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
        array $data,
        bool $preserveTimestamp = true,
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
                // Manual Save should update editorial updated_at; autosave/jobs should not.
                if ($preserveTimestamp) {
                    $this->updateWithoutTouchingTimestamp($article, ['featured_image' => $imageUrl]);
                } else {
                    $article->update(['featured_image' => $imageUrl]);
                }
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

        return $this->sanitizeRichTextFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeRichTextFields(array $data): array
    {
        if (array_key_exists('article_description', $data)) {
            $description = $data['article_description'];
            $data['article_description'] = is_string($description)
                ? $this->articleHtmlSanitizer->sanitize($description)
                : '';
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

        // Once set, published_at is immutable — ignore request body on later saves.
        if ($existing?->published_at) {
            return Carbon::parse($existing->published_at);
        }

        // First publish: due/past schedule wins when present.
        if (! empty($data['scheduled_publishing'])) {
            $scheduledAt = Carbon::parse($data['scheduled_publishing']);
            if ($scheduledAt->lessThanOrEqualTo(now())) {
                return $scheduledAt;
            }
        }

        if (! empty($data['published_at'])) {
            return Carbon::parse($data['published_at']);
        }

        return now();
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

    private function resolveIsBreaking(array $data): bool
    {
        if (! array_key_exists('is_breaking', $data)) {
            return false;
        }

        return filter_var($data['is_breaking'], FILTER_VALIDATE_BOOLEAN);
    }

    private function resolveIsLiveBlog(array $data): bool
    {
        if (! array_key_exists('is_live_blog', $data)) {
            return false;
        }

        return filter_var($data['is_live_blog'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Pull breaking-news fields out of the article payload so they are not mass-assigned.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null  null when the request did not touch breaking news
     */
    private function extractBreakingPayload(array &$data): ?array
    {
        $keys = [
            'is_breaking',
            'breaking_priority',
            'breaking_starts_at',
            'breaking_expires_at',
            'breaking_headline',
            'breaking_status',
        ];

        $touched = false;
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $touched = true;
                break;
            }
        }

        $priority = $data['breaking_priority'] ?? null;
        $startsAt = $data['breaking_starts_at'] ?? null;
        $expiresAt = $data['breaking_expires_at'] ?? null;
        $headline = $data['breaking_headline'] ?? null;
        $status = $data['breaking_status'] ?? null;
        $enabled = array_key_exists('is_breaking', $data)
            ? filter_var($data['is_breaking'], FILTER_VALIDATE_BOOLEAN)
            : null;

        foreach ($keys as $key) {
            unset($data[$key]);
        }

        if (! $touched) {
            return null;
        }

        $payload = [
            'enabled' => $enabled ?? false,
            'starts_at' => $startsAt === '' ? null : $startsAt,
            'expires_at' => $expiresAt === '' ? null : $expiresAt,
            'headline_override' => $headline === '' ? null : $headline,
        ];

        if ($priority !== null && $priority !== '') {
            $payload['priority'] = (int) $priority;
        }

        if ($status !== null && $status !== '') {
            $payload['status'] = $status;
        }

        return $payload;
    }

    /**
     * Keep the canonical breaking-news tag in sync with the is_breaking flag.
     *
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    private function syncBreakingTagWithFlag(array $tags, bool $isBreaking): array
    {
        $breakingLower = array_map('strtolower', BreakingTag::VALUES);

        $normalized = collect($tags)
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->values();

        if ($isBreaking) {
            $hasBreaking = $normalized->contains(
                fn (string $tag) => in_array(strtolower($tag), $breakingLower, true),
            );

            if (! $hasBreaking) {
                $normalized->push('breaking-news');
            }

            return $normalized->unique()->values()->all();
        }

        return $normalized
            ->reject(fn (string $tag) => in_array(strtolower($tag), $breakingLower, true))
            ->values()
            ->all();
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

    /**
     * Manual update should bump updated_at only when editorial fields change.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $incomingTagNames  null when tags were not in the request
     * @param  list<string>  $requestKeys  payload keys before SEO autofill
     */
    private function manualUpdateTouchesEditorialTimestamp(
        Article $article,
        array $attributes,
        ?string $featuredMediaUuid = null,
        ?string $posterMediaUuid = null,
        ?array $incomingTagNames = null,
        array $requestKeys = [],
    ): bool {
        $requestKeySet = array_flip($requestKeys);

        $stringFields = [
            'title',
            'sub_title',
            'article_description',
            'excerpt',
            'slug',
            'live_video_url',
        ];

        foreach ($stringFields as $field) {
            if ($this->nullableStringAttributeChanged($article, $attributes, $field)) {
                return true;
            }
        }

        // SEO meta only counts when the client explicitly sent those fields.
        foreach (['meta_title', 'meta_description', 'meta_keywords'] as $metaField) {
            if (! isset($requestKeySet[$metaField])) {
                continue;
            }
            if ($this->nullableStringAttributeChanged($article, $attributes, $metaField)) {
                return true;
            }
        }

        foreach (['featured_image', 'open_graph_image'] as $imageField) {
            if ($this->nullableStringAttributeChanged($article, $attributes, $imageField)) {
                return true;
            }
        }

        if (array_key_exists('article_category_id', $attributes)) {
            $incoming = $attributes['article_category_id'] !== null
                ? (int) $attributes['article_category_id']
                : null;
            $current = $article->article_category_id !== null
                ? (int) $article->article_category_id
                : null;
            if ($incoming !== $current) {
                return true;
            }
        }

        if ($this->featuredMediaUuidChanged($article, $featuredMediaUuid, $posterMediaUuid)) {
            return true;
        }

        if ($incomingTagNames !== null && $this->tagSetChanged($article, $incomingTagNames)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function nullableStringAttributeChanged(Article $article, array $attributes, string $field): bool
    {
        if (! array_key_exists($field, $attributes)) {
            return false;
        }

        $incoming = $attributes[$field];
        $normalizedIncoming = is_string($incoming) ? $incoming : ($incoming === null ? null : (string) $incoming);
        $current = $article->{$field};
        $normalizedCurrent = is_string($current) ? $current : ($current === null ? null : (string) $current);

        return $normalizedIncoming !== $normalizedCurrent;
    }

    private function featuredMediaUuidChanged(
        Article $article,
        ?string $featuredMediaUuid,
        ?string $posterMediaUuid,
    ): bool {
        if ($featuredMediaUuid !== null) {
            $currentFeatured = $article->featuredMedia()?->uuid;
            $incomingFeatured = $featuredMediaUuid === '' ? null : $featuredMediaUuid;
            if ($incomingFeatured !== $currentFeatured) {
                return true;
            }
        }

        if ($posterMediaUuid !== null) {
            $currentPoster = $article->posterMedia()?->uuid;
            $incomingPoster = $posterMediaUuid === '' ? null : $posterMediaUuid;
            if ($incomingPoster !== $currentPoster) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $incomingTagNames
     */
    private function tagSetChanged(Article $article, array $incomingTagNames): bool
    {
        $normalize = static fn (array $tags): array => collect($tags)
            ->map(fn ($tag) => strtolower(trim((string) $tag)))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $current = $normalize($article->tags()->pluck('tag')->all());
        $incoming = $normalize($incomingTagNames);

        return $current !== $incoming;
    }

}
