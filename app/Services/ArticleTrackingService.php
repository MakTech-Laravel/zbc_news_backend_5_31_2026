<?php
// app/Services/ArticleTrackingService.php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleHistroy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArticleTrackingService
{
    public function track(Request $request, array $data): array
    {
        $userId  = auth('api')->check()
            ? auth('api')->id()
            : ($data['user_id'] ?? null);

        $isGuest = is_null($userId);

        try {
            DB::transaction(function () use ($data, $request, $isGuest, $userId) {

                $alreadyTracked = $this->isSessionAlreadyTracked($data['session_id']);
                if ($alreadyTracked) return;

                $isFirstView = $this->isFirstView(
                    articleId: $data['article_id'],
                    userId: $userId,
                    ip: $request->ip(),
                    isGuest: $isGuest
                );

                $this->saveHistory(
                    data: $data,
                    request: $request,
                    userId: $userId,
                    isGuest: $isGuest
                );

                if ($isFirstView) {
                    $this->incrementViewCount($data['article_id']);
                }
            });

            return ['success' => true, 'message' => 'Tracked successfully.'];
        } catch (\Throwable $e) {
            Log::error('ArticleTrackingService::track failed', [
                'error'      => $e->getMessage(),
                'article_id' => $data['article_id'],
                'user_id'    => $userId,
            ]);

            return ['success' => false, 'message' => 'Tracking failed.'];
        }
    }

    /**
     * article full analytics
     */
    public function getArticleStats(int $articleId): array
    {
        $base = ArticleHistroy::forArticle($articleId);

        return [
            'total_views'        => Article::find($articleId)?->views ?? 0,
            'total_reads'        => (clone $base)->count(),
            'avg_time_spent'     => (clone $base)->avg('time_spent'),
            'avg_scroll_depth'   => (clone $base)->avg('scroll_depth'),
            'auth_reads'         => (clone $base)->authUsers()->count(),
            'guest_reads'        => (clone $base)->guests()->count(),
            'total_time_spent'   => (clone $base)->sum('time_spent'),
        ];
    }

    /**
     * Authenticated user reading history
     */
    public function getUserReadHistory(int $userId, int $perPage = 15)
    {
        return ArticleHistroy::with(['article:id,title,slug,featured_image'])
            ->where('user_id', $userId)
            ->where('is_guest', false)
            ->latest('read_at')
            ->paginate($perPage);
    }

    // ───────────────────────────────────────────
    //  Private Helpers
    // ───────────────────────────────────────────

    /**
     * Same session_id  save
     */
    private function isSessionAlreadyTracked(string $sessionId): bool
    {
        return ArticleHistroy::where('session_id', $sessionId)->exists();
    }

    /**
     * 
     */
    private function isFirstView(
        int     $articleId,
        ?int    $userId,
        string  $ip,
        bool    $isGuest
    ): bool {
        $query = ArticleHistroy::where('article_id', $articleId);

        if ($isGuest) {
            $query->where('ip_address', $ip)->where('is_guest', true);
        } else {
            $query->where('user_id', $userId)->where('is_guest', false);
        }

        return !$query->exists();
    }

    /**
     * History row insert
     */
    private function saveHistory(
        array   $data,
        Request $request,
        ?int    $userId,
        bool    $isGuest
    ): void {
        ArticleHistroy::create([
            'article_id'   => $data['article_id'],
            'user_id'      => $userId,
            'session_id'   => $data['session_id'],
            'ip_address'   => $request->ip(),
            'time_spent'   => $data['time_spent'],
            'scroll_depth' => $data['scroll_depth'],
            'is_guest'     => $isGuest,
            'read_at'      => now(),
            'read_end_at'  => now()->addSeconds($data['time_spent']),
        ]);
    }

    /**
     * Article views count 
     */
    private function incrementViewCount(int $articleId): void
    {
        Article::where('id', $articleId)->increment('views');
    }
}
