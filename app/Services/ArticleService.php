<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Events\NewsPublished;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use App\Support\BreakingTag;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ArticleService
{
    public function __construct(
        private readonly Article $article,
        private readonly SiteSettingsService $siteSettingsService,
        private readonly SeoMetaService $seoMetaService,
    ) {}

    public function getAllArticles()
    {
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
        return $this->articleQuery()->where('slug', $slug)->firstOrFail();
    }

    public function getPublishedBySlug(string $slug): Article
    {
        return $this->articleQuery()
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
            ->with(['tags', 'category', 'user'])
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

    public function create(array $data): Article
    {
        return DB::transaction(function () use ($data) {
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

            $article = $this->article->create($data);

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
                ->log('Article created');

            $article = $article->load('tags');

            if ($article->status === ArticleStatus::PUBLISHED) {
                DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
                $this->broadcastPublishedArticle($article);
            }

            return $article;
        });
    }

    public function update(string $slug, array $data): Article
    {
        $article = $this->article
            ->where('slug', $slug)
            ->firstOrFail();

        return DB::transaction(function () use ($article, $data) {
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

            $old = $article->only([
                'title',
                'slug',
                'status',
                'article_category_id',
                'scheduled_publishing',
                'published_at',
            ]);

            $previousStatus = $article->status;

            $article->update($data);

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
                ->log('Article updated');

            $article = $article->fresh()->load('tags');

            if ($becamePublished) {
                DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
                $this->broadcastPublishedArticle($article);
            } elseif ($article->status === ArticleStatus::PUBLISHED && $contentChanged) {
                DispatchArticlePublishedNotifications::dispatch($article->id, 'updated');
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
            ->with(['tags', 'category', 'user'])
            ->withReadingTime();
    }

    private function resolveSlug(array $data, ?int $excludeId = null): string
    {
        $base = Str::slug(! empty($data['slug']) ? $data['slug'] : $data['title']);
        $slug = $base;
        $count = 1;

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

        return $existing?->published_at ?? now();
    }

    private function resolveImage(
        array $data,
        string $field,
        string $storagePath,
        ?Article $existing = null
    ): ?string {
        if (array_key_exists($field, $data)) {
            $value = $data[$field];

            if ($value instanceof UploadedFile) {
                if ($existing?->{$field}) {
                    $this->deleteStoredImage($existing->{$field});
                }

                $path = $value->store($storagePath, 'public');

                return Storage::url($path);
            }

            if (is_string($value)) {
                $trimmed = trim($value);

                return $trimmed !== '' ? $trimmed : null;
            }

            if ($value === null) {
                return null;
            }
        }

        return $existing?->{$field} ?? null;
    }

    private function deleteStoredImage(?string $storedValue): void
    {
        if (! $storedValue || preg_match('/^https?:\/\//i', $storedValue)) {
            return;
        }

        $oldPath = ltrim(
            parse_url($storedValue, PHP_URL_PATH) ?? $storedValue,
            '/storage/'
        );

        if ($oldPath !== '') {
            Storage::disk('public')->delete($oldPath);
        }
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
                    ->orWhereHas('tags', fn ($tagQuery) => $tagQuery->where('tag', 'like', $like))
                    ->orWhereHas('category', fn ($catQuery) => $catQuery->where('title', 'like', $like));
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
}
