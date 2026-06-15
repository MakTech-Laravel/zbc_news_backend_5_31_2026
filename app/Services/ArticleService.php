<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Tag;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
// use App\Models\ArticleReadLog;
// use Illuminate\Http\Request;

class ArticleService
{
    public function __construct(
        private readonly Article $article
    ) {}

    public function getAllArticles()
    {
        return $this->article->with(['tags', 'category', 'user'])->get();
    }

    public function getTrashedArticles()
    {
        return $this->article
            ->with(['tags', 'category', 'user'])
            ->onlyTrashed()
            ->latest('deleted_at')
            ->get();
    }

    public function getBySlug(string $slug): Article
    {
        return $this->article->with(['tags', 'category', 'user'])->where('slug', $slug)->firstOrFail();
    }

    public function getPublishedBySlug(string $slug): Article
    {
        return $this->article
            ->with(['tags', 'category', 'user'])
            ->where('slug', $slug)
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->firstOrFail();
    }

    // public function incrementViews(Article $article): void
    // {
    //     $article->increment('views');
    // }




    // public function trackView(Article $article, Request $request): void
    // {
    //     DB::transaction(function () use ($article, $request) {
    //         ArticleReadLog::create([
    //             'article_id' => $article->id,
    //             'user_id'    => auth()->id(),
    //             'ip_address' => $request->ip(),
    //             'read_at'    => now(),
    //         ]);

    //         $article->increment('views');
    //     });
    // }


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
            ->limit($limit)
            ->get();
    }

    public function getLatestArticle(): Article
    {
        return $this->article
            ->with(['tags', 'category', 'user'])
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->latest('published_at')
            ->firstOrFail();
    }

    public function getLatestStories(): Collection
    {
        return $this->article
            ->with(['tags', 'category', 'user'])
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->latest('published_at')
            ->take(10)
            ->get();
    }

    public function getLatestArticleByTag(string $tagSlug, string $type = 'latest'): Collection
    {
        $query = $this->article
            ->with(['tags', 'category', 'user'])
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereHas('tags', function ($q) use ($tagSlug) {
                $q->where('tag', $tagSlug);
            });
    
        return match ($type) {
            'trending'    => $query->orderByDesc('views')->take(10)->get(),
            'recommended' => $query->withCount('saveArticles')->orderByDesc('save_articles_count')->take(10)->get(),
            default       => $query->latest('published_at')->take(10)->get(),
        };
    }

    public function getLongReads(string $type = 'all', int $minMinutes = 5): Collection
    {
        $query = $this->article
            ->with(['tags', 'category', 'user'])
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereHas('histroy', function ($q) use ($minMinutes) {
                $q->where('time_spent', '>=', $minMinutes * 60);
            });

        return match ($type) {
            'most-read' => $query->orderByDesc('views')->take(10)->get(),
            default     => $query->latest('published_at')->take(10)->get(), // 'all'
        };
    }

    public function create(array $data): Article
    {
        return DB::transaction(function () use ($data) {
            $tags = $data['tags'] ?? [];
            unset($data['tags']);

            $data['slug']             = $this->resolveSlug($data);
            $data['status']           = $this->resolveStatus($data);
            $data['published_at']     = $this->resolvePublishedAt($data);
            $data['featured_image']   = $this->resolveImage($data, 'featured_image', 'articles/featured-images');
            $data['open_graph_image'] = $this->resolveImage($data, 'open_graph_image', 'articles/og-images');
            $data['user_id']          = auth()->user()->id;

            $article = $this->article->create($data);

            if (!empty($tags)) {
                $tagIds = $this->resolveTags($tags);
                $article->tags()->sync($tagIds);
            }

            activity()
                ->performedOn($article)
                ->causedBy(auth()->user())
                ->withProperties([
                    'article_title'        => $article->title,
                    'article_slug'         => $article->slug,
                    'status'               => $article->status,
                    'article_category_id'  => $article->article_category_id,
                    'tags'                 => $tags,
                    'scheduled_publishing' => $article->scheduled_publishing,
                    'published_at'         => $article->published_at,
                    'ip_address'           => request()->ip(),
                    'user_agent'           => request()->userAgent(),
                ])
                ->log('Article created');

            return $article->load('tags');
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

            $data['slug']             = $this->resolveSlug($data, $article->id);
            $data['status']           = $this->resolveStatus($data);
            $data['published_at']     = $this->resolvePublishedAt($data, $article);
            $data['featured_image']   = $this->resolveImage($data, 'featured_image', 'articles/featured-images', $article);
            $data['open_graph_image'] = $this->resolveImage($data, 'open_graph_image', 'articles/og-images', $article);

            $old = $article->only([
                'title',
                'slug',
                'status',
                'article_category_id',
                'scheduled_publishing',
                'published_at',
            ]);

            $article->update($data);

            if (!is_null($tags)) {
                $tagIds = $this->resolveTags($tags);
                $article->tags()->sync($tagIds);
            }

            activity()
                ->performedOn($article)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old'        => $old,
                    'new'        => $article->fresh()->only(array_keys($old)),
                    'tags'       => $tags,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->log('Article updated');

            return $article->fresh()->load('tags');
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
                'article_slug'  => $article->slug,
                'status'        => $article->status,
                'ip_address'    => request()->ip(),
                'user_agent'    => request()->userAgent(),
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
                'article_slug'  => $article->slug,
                'status'        => $article->status,
                'ip_address'    => request()->ip(),
                'user_agent'    => request()->userAgent(),
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
                'article_slug'  => $article->slug,
                'ip_address'    => request()->ip(),
                'user_agent'    => request()->userAgent(),
            ])
            ->log('Article permanently deleted');

        $article->forceDelete();
    }

    public function getByCategory(string $categorySlug): Collection
    {
        return $this->article
            ->with(['tags', 'category', 'user'])
            ->whereHas('category', function ($query) use ($categorySlug) {
                $query->where('slug', $categorySlug);
            })
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->latest('published_at')
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
                    'id'          => $activity->id,
                    'description' => $activity->description,
                    'event'       => $activity->event,
                    'causer'      => $activity->causer?->name,
                    'old'         => $activity->properties['old'] ?? null,
                    'new'         => $activity->properties['new'] ?? null,
                    'tags'        => $activity->properties['tags'] ?? null,
                    'ip_address'  => $activity->properties['ip_address'] ?? null,
                    'created_at'  => $activity->created_at,
                ];
            });
    }

    // =========================================================================
    // Private Resolvers
    // =========================================================================

    private function resolveSlug(array $data, ?int $excludeId = null): string
    {
        $base  = Str::slug(!empty($data['slug']) ? $data['slug'] : $data['title']);
        $slug  = $base;
        $count = 1;

        while (
            $this->article
            ->where('slug', $slug)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
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

    private function resolvePublishedAt(array $data, ?Article $existing = null): ?\Carbon\Carbon
    {
        $status = $data['status'] ?? ArticleStatus::DRAFT->value;

        return match ($status) {
            ArticleStatus::PUBLISHED->value => isset($data['published_at'])
                ? \Carbon\Carbon::parse($data['published_at'])
                : ($existing?->published_at ?? now()),
            default => null,
        };
    }

    private function resolveImage(
        array $data,
        string $field,
        string $storagePath,
        ?Article $existing = null
    ): ?string {
        if (!empty($data[$field]) && $data[$field] instanceof UploadedFile) {
            if ($existing?->{$field}) {
                $oldPath = ltrim(
                    parse_url($existing->{$field}, PHP_URL_PATH),
                    '/storage/'
                );
                Storage::disk('public')->delete($oldPath);
            }

            $path = $data[$field]->store($storagePath, 'public');

            return Storage::url($path);
        }

        return $existing?->{$field} ?? null;
    }

    private function resolveTags(array $tags): array
    {
        return collect($tags)
            ->map(fn($tagName) => Tag::firstOrCreate(['tag' => strtolower(trim($tagName))])->id)
            ->toArray();
    }

    public function getGridArticles(int $limit = 50, array $excludeIds = []): Collection
    {
        return $this->article
            ->with(['tags', 'category', 'user'])
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereNotIn('id', $excludeIds)
            ->orderByDesc('views')
            ->take($limit)
            ->get();
    }
}
