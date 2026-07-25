<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\BreakingNewsStatus;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;
use App\Models\BreakingNewsItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BreakingNewsService
{
    public function listForTicker(int $limit = 10): Collection
    {
        $this->expireDueItems();

        $limit = min(max($limit, 1), 10);

        return BreakingNewsItem::query()
            ->with([
                'article' => fn ($q) => $q->with([
                    'category:id,title,slug',
                    'user:id,name,slug',
                    'tags:id,tag',
                    'media' => fn ($media) => $media->where('status', 'ready')
                        ->whereIn('collection', ['featured', 'poster']),
                ]),
            ])
            ->eligibleForTicker()
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{status?: string, search?: string}  $filters
     */
    public function listForAdmin(array $filters = []): Collection
    {
        $this->expireDueItems();

        $query = BreakingNewsItem::query()
            ->with([
                'article.category:id,title,slug',
                'article.user:id,name,slug',
                'creator:id,name',
            ])
            ->where('status', '!=', BreakingNewsStatus::REMOVED->value)
            ->orderBy('priority')
            ->orderByDesc('updated_at');

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $status = $filters['status'];
            if ($status === 'scheduled') {
                $query->where('status', BreakingNewsStatus::ACTIVE->value)
                    ->whereNotNull('starts_at')
                    ->where('starts_at', '>', now());
            } else {
                $query->where('status', $status);
            }
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim($filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('headline_override', 'like', $term)
                    ->orWhereHas('article', function ($articleQuery) use ($term) {
                        $articleQuery->where('title', 'like', $term)
                            ->orWhere('slug', 'like', $term);
                    });
            });
        }

        return $query->get();
    }

    public function findOrFail(int $id): BreakingNewsItem
    {
        return BreakingNewsItem::query()
            ->with(['article.category', 'article.user', 'creator'])
            ->findOrFail($id);
    }

    /**
     * Upsert a breaking item for an article (create/edit article flow).
     *
     * @param  array{
     *   enabled?: bool,
     *   priority?: int,
     *   starts_at?: mixed,
     *   expires_at?: mixed,
     *   headline_override?: string|null,
     *   status?: string,
     * }  $data
     */
    public function syncForArticle(Article $article, array $data, ?int $actorId = null): ?BreakingNewsItem
    {
        $enabled = array_key_exists('enabled', $data)
            ? filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN)
            : true;

        if (! $enabled) {
            $this->removeForArticle($article);

            return null;
        }

        $item = BreakingNewsItem::query()->firstOrNew(['article_id' => $article->id]);

        $wasLive = $item->exists && $item->isLive();

        $status = $data['status'] ?? ($item->exists ? $item->status->value : BreakingNewsStatus::ACTIVE->value);
        if (! in_array($status, [
            BreakingNewsStatus::ACTIVE->value,
            BreakingNewsStatus::PAUSED->value,
        ], true)) {
            $status = BreakingNewsStatus::ACTIVE->value;
        }

        // Re-enable from expired/removed → active.
        if (in_array($item->status?->value, [
            BreakingNewsStatus::EXPIRED->value,
            BreakingNewsStatus::REMOVED->value,
        ], true)) {
            $status = BreakingNewsStatus::ACTIVE->value;
            $item->notified_at = null;
        }

        $item->fill([
            'headline_override' => array_key_exists('headline_override', $data)
                ? ($data['headline_override'] !== null && $data['headline_override'] !== ''
                    ? (string) $data['headline_override']
                    : null)
                : $item->headline_override,
            'priority' => isset($data['priority'])
                ? max(0, (int) $data['priority'])
                : ($item->exists ? $item->priority : $this->nextPriority()),
            'status' => $status,
            'starts_at' => array_key_exists('starts_at', $data)
                ? $this->parseNullableDate($data['starts_at'])
                : $item->starts_at,
            'expires_at' => array_key_exists('expires_at', $data)
                ? $this->parseNullableDate($data['expires_at'])
                : $item->expires_at,
        ]);

        if (! $item->exists) {
            $item->created_by = $actorId ?? auth()->id();
        }

        if (
            $item->expires_at
            && $item->status === BreakingNewsStatus::ACTIVE
            && $item->expires_at->lessThanOrEqualTo(now())
        ) {
            $item->status = BreakingNewsStatus::EXPIRED;
        }

        $item->save();

        $this->syncArticleBreakingFlag(
            $article,
            $item->status !== BreakingNewsStatus::REMOVED,
        );

        $item->load('article');

        if (! $wasLive && $item->isLive() && ! $item->notified_at) {
            $this->notifyIfNeeded($item);
        }

        return $item->fresh(['article.category', 'article.user', 'creator']);
    }

    public function update(BreakingNewsItem $item, array $data): BreakingNewsItem
    {
        $wasLive = $item->isLive();

        if (array_key_exists('headline_override', $data)) {
            $value = $data['headline_override'];
            $item->headline_override = $value !== null && $value !== '' ? (string) $value : null;
        }

        if (array_key_exists('priority', $data)) {
            $item->priority = max(0, (int) $data['priority']);
        }

        if (array_key_exists('starts_at', $data)) {
            $item->starts_at = $this->parseNullableDate($data['starts_at']);
        }

        if (array_key_exists('expires_at', $data)) {
            $item->expires_at = $this->parseNullableDate($data['expires_at']);
        }

        if (array_key_exists('status', $data) && in_array($data['status'], [
            BreakingNewsStatus::ACTIVE->value,
            BreakingNewsStatus::PAUSED->value,
        ], true)) {
            $item->status = BreakingNewsStatus::from($data['status']);
        }

        if (
            $item->status === BreakingNewsStatus::ACTIVE
            && $item->expires_at
            && $item->expires_at->lessThanOrEqualTo(now())
        ) {
            $item->status = BreakingNewsStatus::EXPIRED;
        }

        $item->save();

        if ($item->article) {
            $this->syncArticleBreakingFlag(
                $item->article,
                $item->status !== BreakingNewsStatus::REMOVED
                    && $item->status !== BreakingNewsStatus::EXPIRED,
            );
        }

        $item->load('article');

        if (! $wasLive && $item->isLive() && ! $item->notified_at) {
            $this->notifyIfNeeded($item);
        }

        return $item->fresh(['article.category', 'article.user', 'creator']);
    }

    public function activate(BreakingNewsItem $item): BreakingNewsItem
    {
        return $this->update($item, ['status' => BreakingNewsStatus::ACTIVE->value]);
    }

    public function pause(BreakingNewsItem $item): BreakingNewsItem
    {
        $item->status = BreakingNewsStatus::PAUSED;
        $item->save();

        return $item->fresh(['article.category', 'article.user', 'creator']);
    }

    public function remove(BreakingNewsItem $item): void
    {
        $item->status = BreakingNewsStatus::REMOVED;
        $item->save();

        if ($item->article) {
            $this->syncArticleBreakingFlag($item->article, false);
        }
    }

    public function removeForArticle(Article $article): void
    {
        $item = BreakingNewsItem::query()->where('article_id', $article->id)->first();
        if ($item) {
            $this->remove($item);
        } else {
            $this->syncArticleBreakingFlag($article, false);
        }
    }

    /**
     * @param  array<int, int>  $orderedIds  Breaking item IDs in desired order (first = highest priority)
     */
    public function reorder(array $orderedIds): Collection
    {
        DB::transaction(function () use ($orderedIds) {
            $priority = 10;
            foreach ($orderedIds as $id) {
                BreakingNewsItem::query()
                    ->whereKey($id)
                    ->where('status', '!=', BreakingNewsStatus::REMOVED->value)
                    ->update(['priority' => $priority]);
                $priority += 10;
            }
        });

        return $this->listForAdmin();
    }

    public function expireDueItems(): int
    {
        $count = BreakingNewsItem::query()
            ->where('status', BreakingNewsStatus::ACTIVE->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => BreakingNewsStatus::EXPIRED->value]);

        if ($count > 0) {
            $expiredArticleIds = BreakingNewsItem::query()
                ->where('status', BreakingNewsStatus::EXPIRED->value)
                ->pluck('article_id');

            // Bulk flag clear must not bump editorial updated_at.
            Article::withoutTimestamps(function () use ($expiredArticleIds) {
                Article::query()
                    ->whereIn('id', $expiredArticleIds)
                    ->where('is_breaking', true)
                    ->update(['is_breaking' => false]);
            });
        }

        return $count;
    }

    /**
     * Keep denormalized articles.is_breaking in sync without changing updated_at.
     * Editorial updated_at is reserved for article-panel Save only.
     */
    private function syncArticleBreakingFlag(Article $article, bool $isBreaking): void
    {
        if ((bool) $article->is_breaking === $isBreaking) {
            return;
        }

        $article->timestamps = false;

        try {
            $article->forceFill(['is_breaking' => $isBreaking])->save();
        } finally {
            $article->timestamps = true;
        }
    }

    private function nextPriority(): int
    {
        $max = (int) BreakingNewsItem::query()
            ->where('status', '!=', BreakingNewsStatus::REMOVED->value)
            ->max('priority');

        return $max > 0 ? $max + 10 : 10;
    }

    private function parseNullableDate(mixed $value): ?\Carbon\Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \Carbon\Carbon::parse($value);
    }

    private function notifyIfNeeded(BreakingNewsItem $item): void
    {
        $article = $item->article;
        if (! $article || $article->status !== ArticleStatus::PUBLISHED) {
            return;
        }

        $item->forceFill(['notified_at' => now()])->save();
        DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
    }
}
