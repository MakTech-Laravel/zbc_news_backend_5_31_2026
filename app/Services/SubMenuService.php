<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\CommentStatus;
use App\Enums\SubMenuKey;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Models\Article;
use App\Models\SubMenuFeaturedArticle;
use App\Models\SubMenuSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SubMenuService
{
    public function flushPublicCache(): void
    {
        foreach (SubMenuKey::cases() as $section) {
            Cache::forget(SubMenuSetting::cacheKey($section->value, 'public'));
        }
        Cache::forget(SubMenuSetting::cacheKey('all', 'public'));
    }

    public function getSettings(string|SubMenuKey $section): SubMenuSetting
    {
        $key = $section instanceof SubMenuKey ? $section->value : $section;

        $setting = SubMenuSetting::query()->where('section_key', $key)->first();
        if ($setting) {
            return $setting;
        }

        return SubMenuSetting::query()->create([
            'section_key' => $key,
            'limit' => 5,
            'trending_window_hours' => 24,
            'most_read_default_period' => 'today',
            'pinned_slots' => 0,
            'is_enabled' => true,
            'config' => null,
        ]);
    }

    /**
     * @return array{settings: SubMenuSetting, manual: Collection<int, SubMenuFeaturedArticle>, algorithmic: Collection<int, Article>, merged: Collection<int, Article>}
     */
    public function adminSnapshot(string|SubMenuKey $section): array
    {
        $key = $section instanceof SubMenuKey ? $section->value : $section;
        $settings = $this->getSettings($key);
        $manual = $this->manualEntries($key, includeInactive: true);
        $algorithmic = $this->algorithmicArticles($key, (int) $settings->limit);

        $manualArticleIds = $manual->pluck('article_id')->map(fn ($id) => (int) $id)->all();
        $merged = $this->mergeManualWithAlgorithmic(
            $manual->filter(fn (SubMenuFeaturedArticle $entry) => $entry->isCurrentlyActive()),
            $algorithmic->reject(fn (Article $article) => in_array((int) $article->id, $manualArticleIds, true)),
            (int) $settings->limit,
            max(0, (int) $settings->pinned_slots),
        );

        return [
            'settings' => $settings,
            'manual' => $manual,
            'algorithmic' => $algorithmic,
            'merged' => $merged,
        ];
    }

    /**
     * @return array{section: string, settings: array<string,mixed>, manual: array<int,array<string,mixed>>, algorithmic: array<int,array<string,mixed>>, items: array<int,array<string,mixed>>}
     */
    public function publicSection(string|SubMenuKey $section): array
    {
        $key = $section instanceof SubMenuKey ? $section->value : $section;

        return Cache::remember(
            SubMenuSetting::cacheKey($key, 'public'),
            SubMenuSetting::TTL_PUBLIC,
            function () use ($key) {
                $snapshot = $this->adminSnapshot($key);
                $enabled = (bool) $snapshot['settings']->is_enabled;
                $activeManual = $enabled
                    ? $snapshot['manual']
                        ->filter(fn (SubMenuFeaturedArticle $entry) => $entry->isCurrentlyActive())
                        ->values()
                    : new Collection();

                return [
                    'section' => $key,
                    'settings' => [
                        'limit' => (int) $snapshot['settings']->limit,
                        'trending_window_hours' => (int) $snapshot['settings']->trending_window_hours,
                        'most_read_default_period' => (string) $snapshot['settings']->most_read_default_period,
                        'pinned_slots' => (int) $snapshot['settings']->pinned_slots,
                        'is_enabled' => $enabled,
                        'config' => $snapshot['settings']->config,
                    ],
                    'manual' => $activeManual->map(fn (SubMenuFeaturedArticle $entry) => $this->serializeManualEntry($entry))->values()->all(),
                    'algorithmic' => $enabled
                        ? $snapshot['algorithmic']->map(fn (Article $article) => $this->serializeArticle($article))->values()->all()
                        : [],
                    'items' => $enabled
                        ? $snapshot['merged']->map(fn (Article $article) => $this->serializeArticle($article))->values()->all()
                        : [],
                ];
            },
        );
    }

    public function updateSettings(string|SubMenuKey $section, array $payload): SubMenuSetting
    {
        $settings = $this->getSettings($section);

        $settings->fill(array_intersect_key($payload, array_flip([
            'limit',
            'trending_window_hours',
            'most_read_default_period',
            'pinned_slots',
            'is_enabled',
            'config',
        ])));

        $settings->save();
        $this->flushPublicCache();

        return $settings->fresh();
    }

    public function upsertManualEntry(string|SubMenuKey $section, array $payload, ?int $actorId = null): SubMenuFeaturedArticle
    {
        $key = $section instanceof SubMenuKey ? $section->value : $section;

        $entry = SubMenuFeaturedArticle::query()->firstOrNew([
            'section_key' => $key,
            'article_id' => (int) $payload['article_id'],
        ]);

        $entry->fill([
            'sort_order' => isset($payload['sort_order']) ? max(0, (int) $payload['sort_order']) : ($entry->exists ? $entry->sort_order : 0),
            'is_pinned' => array_key_exists('is_pinned', $payload)
                ? (bool) filter_var($payload['is_pinned'], FILTER_VALIDATE_BOOLEAN)
                : ($entry->exists ? (bool) $entry->is_pinned : false),
            'is_active' => array_key_exists('is_active', $payload)
                ? (bool) filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN)
                : ($entry->exists ? (bool) $entry->is_active : true),
            'starts_at' => array_key_exists('starts_at', $payload) ? $payload['starts_at'] : $entry->starts_at,
            'ends_at' => array_key_exists('ends_at', $payload) ? $payload['ends_at'] : $entry->ends_at,
        ]);

        if (! $entry->exists) {
            $entry->created_by = $actorId;
        }

        $entry->save();
        $this->flushPublicCache();

        return $entry->fresh(['article.category', 'article.user', 'creator']);
    }

    public function removeManualEntry(int $id): void
    {
        $entry = SubMenuFeaturedArticle::query()->findOrFail($id);
        $entry->delete();
        $this->flushPublicCache();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, SubMenuFeaturedArticle>
     */
    public function reorderManualEntries(string|SubMenuKey $section, array $ids): Collection
    {
        $key = $section instanceof SubMenuKey ? $section->value : $section;

        return DB::transaction(function () use ($key, $ids) {
            foreach ($ids as $index => $id) {
                SubMenuFeaturedArticle::query()
                    ->where('section_key', $key)
                    ->where('id', (int) $id)
                    ->update(['sort_order' => ($index + 1) * 10]);
            }

            $this->flushPublicCache();

            return SubMenuFeaturedArticle::query()
                ->forSection($key)
                ->with(['article.category', 'article.user', 'creator'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    public function startLiveCoverage(Article $article): Article
    {
        $article->is_live = true;
        $article->live_started_at = now();
        $article->live_ended_at = null;
        $article->save();

        $this->flushPublicCache();

        return $article->fresh();
    }

    public function endLiveCoverage(Article $article): Article
    {
        $article->is_live = false;
        $article->live_ended_at = now();
        $article->save();

        $this->flushPublicCache();

        return $article->fresh();
    }

    public function processScheduledWindows(): int
    {
        $now = now();

        $expired = SubMenuFeaturedArticle::query()
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->update(['is_active' => false]);

        $endedLive = Article::query()
            ->where('is_live', true)
            ->whereNotNull('live_ended_at')
            ->where('live_ended_at', '<=', $now)
            ->update(['is_live' => false]);

        if ($expired > 0 || $endedLive > 0) {
            $this->flushPublicCache();
        }

        return $expired + $endedLive;
    }

    /**
     * @return Collection<int, SubMenuFeaturedArticle>
     */
    private function manualEntries(string $section, bool $includeInactive = false): Collection
    {
        $query = SubMenuFeaturedArticle::query()
            ->forSection($section)
            ->with([
                'article' => fn ($q) => $q
                    ->where('status', ArticleStatus::PUBLISHED->value)
                    ->whereNull('deleted_at')
                    ->with(['category', 'user']),
                'creator:id,name',
            ])
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! $includeInactive) {
            $query->currentlyActive();
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Article>
     */
    private function algorithmicArticles(string $section, int $limit): Collection
    {
        return match ($section) {
            SubMenuKey::TRENDING->value => $this->trendingArticles($limit),
            SubMenuKey::MOST_READ->value => $this->mostReadArticles($limit),
            SubMenuKey::LIVE_UPDATES->value => $this->liveArticles($limit),
            SubMenuKey::EDITORIAL_PICKS->value => $this->latestPublishedArticles($limit),
            default => new Collection(),
        };
    }

    /** @return Collection<int, Article> */
    private function trendingArticles(int $limit): Collection
    {
        $setting = $this->getSettings(SubMenuKey::TRENDING);
        $hours = max(1, (int) $setting->trending_window_hours);
        $since = now()->subHours($hours);

        return Article::query()
            ->where('articles.status', ArticleStatus::PUBLISHED->value)
            ->whereNull('articles.deleted_at')
            ->leftJoin('article_histroys as ah', function ($join) use ($since) {
                $join->on('articles.id', '=', 'ah.article_id')
                    ->where('ah.read_at', '>=', $since);
            })
            ->select('articles.*')
            ->selectRaw('COUNT(ah.id) as rolling_views')
            ->selectRaw('COUNT(DISTINCT COALESCE(ah.user_id, ah.ip_address)) as rolling_unique_views')
            ->withCount([
                'comments as comments_count' => fn ($q) => $q->where('status', CommentStatus::APPROVED),
                'saveArticles as saves_count',
            ])
            ->groupBy('articles.id')
            ->orderByDesc('rolling_unique_views')
            ->orderByDesc('comments_count')
            ->orderByDesc('saves_count')
            ->orderByDesc('rolling_views')
            ->limit($limit)
            ->with(['category', 'user'])
            ->get();
    }

    /** @return Collection<int, Article> */
    private function mostReadArticles(int $limit): Collection
    {
        $setting = $this->getSettings(SubMenuKey::MOST_READ);

        $period = match ((string) $setting->most_read_default_period) {
            'week' => 'week',
            'month' => 'month',
            'all' => 'all',
            default => 'today',
        };

        $baseQuery = Article::query()
            ->where('articles.status', ArticleStatus::PUBLISHED->value)
            ->whereNull('articles.deleted_at')
            ->join('article_histroys as ah', 'articles.id', '=', 'ah.article_id')
            ->select('articles.*')
            ->selectRaw('COUNT(DISTINCT COALESCE(ah.user_id, ah.ip_address)) as read_count');

        $baseQuery->when($period !== 'all', function ($q) use ($period) {
            $since = match ($period) {
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                default => now()->startOfDay(),
            };
            $q->where('ah.read_at', '>=', $since);
        });

        return $baseQuery
            ->groupBy('articles.id')
            ->orderByDesc('read_count')
            ->limit($limit)
            ->with(['category', 'user'])
            ->get();
    }

    /** @return Collection<int, Article> */
    private function liveArticles(int $limit): Collection
    {
        return Article::query()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereNull('deleted_at')
            ->where('is_live', true)
            ->orderByDesc('live_started_at')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->with(['category', 'user'])
            ->get();
    }

    /** @return Collection<int, Article> */
    private function latestPublishedArticles(int $limit): Collection
    {
        return Article::query()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereNull('deleted_at')
            ->latest('published_at')
            ->limit($limit)
            ->with(['category', 'user'])
            ->get();
    }

    /**
     * @param  Collection<int, SubMenuFeaturedArticle>  $manualEntries
     * @param  Collection<int, Article>  $algorithmic
     * @return Collection<int, Article>
     */
    private function mergeManualWithAlgorithmic(
        Collection $manualEntries,
        Collection $algorithmic,
        int $limit,
        int $pinnedSlots,
    ): Collection {
        $articles = new Collection();
        $seenIds = [];

        $push = function (Article $article) use (&$articles, &$seenIds, $limit): bool {
            $id = (int) $article->id;
            if (isset($seenIds[$id]) || $articles->count() >= $limit) {
                return false;
            }
            $seenIds[$id] = true;
            $articles->push($article);

            return true;
        };

        $pinned = $manualEntries
            ->filter(fn (SubMenuFeaturedArticle $entry) => $entry->is_pinned && $entry->article !== null)
            ->sortBy('sort_order')
            ->values();

        $pinnedTaken = 0;
        foreach ($pinned as $entry) {
            if ($articles->count() >= $limit) {
                break;
            }
            if ($pinnedSlots > 0 && $pinnedTaken >= $pinnedSlots) {
                break;
            }
            if ($pinnedSlots === 0) {
                break;
            }
            if ($push($entry->article)) {
                $pinnedTaken++;
            }
        }

        // Remaining manual entries (unpinned, or pinned beyond reserved slots / when slots=0).
        $remainingManual = $manualEntries
            ->filter(fn (SubMenuFeaturedArticle $entry) => $entry->article !== null)
            ->sortBy('sort_order')
            ->values();

        foreach ($remainingManual as $entry) {
            if ($articles->count() >= $limit) {
                break;
            }
            $push($entry->article);
        }

        foreach ($algorithmic as $article) {
            if ($articles->count() >= $limit) {
                break;
            }
            $push($article);
        }

        return $articles->values();
    }

    /** @return array<string,mixed> */
    private function serializeManualEntry(SubMenuFeaturedArticle $entry): array
    {
        $article = $entry->relationLoaded('article') ? $entry->article : null;

        return [
            'id' => (int) $entry->id,
            'section_key' => $entry->section_key?->value ?? (string) $entry->section_key,
            'article_id' => (int) $entry->article_id,
            'sort_order' => (int) $entry->sort_order,
            'is_pinned' => (bool) $entry->is_pinned,
            'is_active' => (bool) $entry->is_active,
            'starts_at' => $entry->starts_at?->toIso8601String(),
            'ends_at' => $entry->ends_at?->toIso8601String(),
            'created_by' => $entry->created_by,
            'article' => $article ? $this->serializeArticle($article) : null,
        ];
    }

    /**
     * Cache-safe article payload (JSON round-trip avoids Eloquent / enum / Carbon in cache).
     *
     * @return array<string,mixed>
     */
    private function serializeArticle(Article $article): array
    {
        $payload = json_decode((new ArticleResource($article))->toJson(), true);

        return is_array($payload) ? $payload : ['id' => (int) $article->id];
    }
}