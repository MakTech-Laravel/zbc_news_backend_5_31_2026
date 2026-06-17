<?php

namespace App\Services;

use App\Models\SearchHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchService
{
    public function __construct(
        private readonly ArticleService $articleService,
    ) {}

    public function searchArticles(string $query, int $limit = 10): Collection
    {
        return $this->articleService->searchPublished($query, $limit);
    }

    public function getHistory(Request $request, int $limit = 8): array
    {
        $userId = auth('api')->id();
        $sessionId = $this->resolveSessionId($request);

        if (! $userId && ! $sessionId) {
            return [];
        }

        $query = SearchHistory::query()->orderByDesc('searched_at')->limit($limit);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId)->whereNull('user_id');
        }

        return $query->get()
            ->map(fn (SearchHistory $row) => [
                'id' => $row->id,
                'query' => $row->query,
                'searched_at' => $row->searched_at?->diffForHumans() ?? '',
            ])
            ->values()
            ->all();
    }

    public function recordHistory(Request $request, string $query): void
    {
        $term = trim($query);
        if ($term === '' || mb_strlen($term) < 2) {
            return;
        }

        $userId = auth('api')->id();
        $sessionId = $this->resolveSessionId($request);

        if (! $userId && ! $sessionId) {
            return;
        }

        $existing = SearchHistory::query();

        if ($userId) {
            $existing->where('user_id', $userId)->where('query', $term);
        } else {
            $existing->where('session_id', $sessionId)->whereNull('user_id')->where('query', $term);
        }

        $existing->delete();

        SearchHistory::query()->create([
            'user_id' => $userId,
            'session_id' => $userId ? null : $sessionId,
            'query' => $term,
            'ip_address' => $request->ip(),
            'searched_at' => now(),
        ]);

        $this->pruneHistory($userId, $sessionId);
    }

    public function clearHistory(Request $request): void
    {
        $userId = auth('api')->id();
        $sessionId = $this->resolveSessionId($request);

        if ($userId) {
            SearchHistory::query()->where('user_id', $userId)->delete();
            return;
        }

        if ($sessionId) {
            SearchHistory::query()
                ->where('session_id', $sessionId)
                ->whereNull('user_id')
                ->delete();
        }
    }

    private function pruneHistory(?int $userId, ?string $sessionId, int $max = 20): void
    {
        $query = SearchHistory::query()->orderByDesc('searched_at');

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId)->whereNull('user_id');
        } else {
            return;
        }

        $idsToKeep = $query->limit($max)->pluck('id');

        $deleteQuery = SearchHistory::query();

        if ($userId) {
            $deleteQuery->where('user_id', $userId)->whereNotIn('id', $idsToKeep);
        } else {
            $deleteQuery->where('session_id', $sessionId)->whereNull('user_id')->whereNotIn('id', $idsToKeep);
        }

        $deleteQuery->delete();
    }

    private function resolveSessionId(Request $request): ?string
    {
        $sessionId = (string) $request->header('X-Search-Session-Id', '');

        return $sessionId !== '' ? $sessionId : null;
    }
}
