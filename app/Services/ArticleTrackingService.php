<?php
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


    // app/Services/ArticleTrackingService.php

    public function getUserReadingAnalytics(int $userId): array
    {
        $base = ArticleHistroy::query()
            ->where('article_histroys.user_id', $userId)
            ->where('article_histroys.is_guest', false);

        // ── Stats Cards ──────────────────────────────
        $thisWeekStart = now()->startOfWeek();
        $thisMonthStart = now()->startOfMonth();

        $articlesThisWeek = (clone $base)
            ->where('read_at', '>=', $thisWeekStart)
            ->count();

        $readingTimeThisWeek = (clone $base)
            ->where('read_at', '>=', $thisWeekStart)
            ->sum('time_spent'); // seconds

        $avgPerDay = (clone $base)
            ->where('read_at', '>=', $thisMonthStart)
            ->selectRaw('DATE(read_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->avg('count');

        $completionRate = (clone $base)
            ->where('read_at', '>=', $thisMonthStart)
            ->avg('scroll_depth'); // scroll_depth = completion %

        // ── Weekly Activity (Mon–Sun) ─────────────────
        $weeklyActivity = (clone $base)
            ->where('read_at', '>=', $thisWeekStart)
            ->selectRaw('DAYNAME(read_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $weeklyData = collect($days)->map(fn($d) => [
            'day'   => substr($d, 0, 3),
            'count' => $weeklyActivity[$d] ?? 0,
        ])->values();

        // ── Reading by Category ───────────────────────
        $byCategory = (clone $base)
            ->join('articles', 'article_histroys.article_id', '=', 'articles.id')
            ->join('article_categories', 'articles.article_category_id', '=', 'article_categories.id')
            ->selectRaw('article_categories.title as category, COUNT(*) as count')
            ->groupBy('article_categories.title')
            ->orderByDesc('count')
            ->get();

        $totalCategoryReads = $byCategory->sum('count');
        $categoryData = $byCategory->map(fn($item) => [
            'category'   => $item->category,
            'count'      => $item->count,
            'percentage' => $totalCategoryReads > 0
                ? round(($item->count / $totalCategoryReads) * 100)
                : 0,
        ])->values();

        // ── Reading Trend (Monthly) ───────────────────
        $monthlyTrend = (clone $base)
            ->where('read_at', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(read_at, "%b") as month, MONTH(read_at) as month_num, COUNT(*) as count')
            ->groupByRaw('month, month_num')
            ->orderBy('month_num')
            ->get()
            ->map(fn($item) => [
                'month' => $item->month,
                'count' => $item->count,
            ])->values();

        // ── Most Engaged Articles ─────────────────────
        $mostEngaged = (clone $base)
            ->join('articles', 'article_histroys.article_id', '=', 'articles.id')
            ->join('article_categories', 'articles.article_category_id', '=', 'article_categories.id')
            ->select(
                'articles.title',
                'articles.slug',
                'article_categories.title as category',
                'article_histroys.scroll_depth',
                'article_histroys.time_spent',
                'article_histroys.read_at',
            )
            ->orderByDesc('article_histroys.scroll_depth')
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'title'      => $item->title,
                'slug'       => $item->slug,
                'category'   => $item->category,
                'completion' => $item->scroll_depth,
                'read_time'  => ceil($item->time_spent / 60) . ' min read',
            ])->values();

        return [
            'stats' => [
                'articles_this_week'   => $articlesThisWeek,
                'reading_time_this_week' => round($readingTimeThisWeek / 3600, 1), // hours
                'avg_per_day'          => round($avgPerDay ?? 0, 1),
                'completion_rate'      => round($completionRate ?? 0),
            ],
            'weekly_activity' => $weeklyData,
            'by_category'     => $categoryData,
            'monthly_trend'   => $monthlyTrend,
            'most_engaged'    => $mostEngaged,
        ];
    }
}
