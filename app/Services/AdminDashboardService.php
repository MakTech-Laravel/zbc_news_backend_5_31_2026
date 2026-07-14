<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\ContactInquiryStatus;
use App\Models\Article;
use App\Models\ArticleHistroy;
use App\Models\ContactInquiry;
use App\Models\User;
use Carbon\Carbon;

class AdminDashboardService
{
    public function getOverview(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
        $weekStart = $now->copy()->startOfWeek();
        $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
        $lastWeekEnd = $now->copy()->subWeek()->endOfWeek();

        // ── Published Articles ────────────────────────────
        $publishedNow = Article::where('status', ArticleStatus::PUBLISHED->value)->count();
        $publishedLast = Article::where('status', ArticleStatus::PUBLISHED->value)
            ->whereBetween('published_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        // ── Active Users (registered last 30 days as proxy) ──
        $activeUsers = User::where('created_at', '>=', $now->copy()->subDays(30))->count();
        $activeUsersLast = User::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $totalUsers = User::count();

        // ── Page Views (total article views) ─────────────────
        $totalViews = Article::sum('views');
        $viewsThisMonth = ArticleHistroy::where('read_at', '>=', $monthStart)->count();
        $viewsLastMonth = ArticleHistroy::whereBetween('read_at', [$lastMonthStart, $lastMonthEnd])->count();

        // ── Revenue MTD from monetization service ─────────────
        $monetization = app(MonetizationAnalyticsService::class)->getOverview();
        $revenueMtd = $monetization['total_revenue'] ?? 0;
        $revenuePct = $monetization['revenue_change_pct'] ?? 0;

        // ── Draft / Scheduled ─────────────────────────────────
        $drafts = Article::where('status', ArticleStatus::DRAFT->value)->count();
        $scheduled = Article::where('status', ArticleStatus::SCHEDULED->value)->count();
        $newContactMessages = ContactInquiry::query()
            ->where('status', ContactInquiryStatus::NEW)
            ->count();

        // ── Engagement Rate (avg scroll depth this month) ─────
        $engagementRate = ArticleHistroy::where('read_at', '>=', $monthStart)
            ->avg('scroll_depth') ?? 0;
        $engagementLast = ArticleHistroy::whereBetween('read_at', [$lastMonthStart, $lastMonthEnd])
            ->avg('scroll_depth') ?? 0;

        // ── Traffic (last 7 days, unique sessions + total reads) ─
        $trafficData = $this->getWeeklyTraffic();

        // ── Revenue chart (last 6 months) ─────────────────────
        $revenueChart = $this->getMonthlyRevenue();

        // ── Recent Articles ───────────────────────────────────
        $recentArticles = $this->getRecentArticles();

        // ── Top Performing Articles ───────────────────────────
        $topArticles = $this->getTopArticles();

        return [
            'primary_metrics' => [
                [
                    'label' => 'Published Articles',
                    'value' => $publishedNow,
                    'trend' => $this->pct($publishedNow, $publishedLast),
                    'iconTone' => 'blue',
                ],
                [
                    'label' => 'Active Users',
                    'value' => $totalUsers,
                    'trend' => $this->pct($activeUsers, $activeUsersLast),
                    'iconTone' => 'green',
                ],
                [
                    'label' => 'Total Page Views',
                    'value' => $totalViews,
                    'trend' => $this->pct($viewsThisMonth, $viewsLastMonth),
                    'iconTone' => 'purple',
                ],
                [
                    'label' => 'Revenue (MTD)',
                    'value' => '$'.number_format($revenueMtd / 100, 0),
                    'trend' => ($revenuePct >= 0 ? '+' : '').number_format($revenuePct, 1).'%',
                    'iconTone' => 'orange',
                ],
            ],
            'secondary_metrics' => [
                [
                    'label' => 'Draft Articles',
                    'value' => $drafts,
                    'iconTone' => 'yellow',
                ],
                [
                    'label' => 'Scheduled Posts',
                    'value' => $scheduled,
                    'iconTone' => 'indigo',
                ],
                [
                    'label' => 'Engagement Rate',
                    'value' => round($engagementRate).'%',
                    'trend' => $this->pct((int) $engagementRate, (int) $engagementLast),
                    'iconTone' => 'red',
                ],
                [
                    'label' => 'New Contact Messages',
                    'value' => $newContactMessages,
                    'iconTone' => 'blue',
                ],
            ],
            'contact_messages' => [
                'new_count' => $newContactMessages,
            ],
            'traffic_chart' => $trafficData,
            'revenue_chart' => $revenueChart,
            'recent_articles' => $recentArticles,
            'top_articles' => $topArticles,
        ];
    }

    private function pct(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? '+100%' : '0%';
        }
        $pct = (($current - $previous) / $previous) * 100;

        return ($pct >= 0 ? '+' : '').number_format($pct, 1).'%';
    }

    private function getWeeklyTraffic(): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $days[] = now()->subDays($i)->toDateString();
        }

        $pageViews = ArticleHistroy::selectRaw('DATE(read_at) as date, COUNT(*) as count')
            ->where('read_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->pluck('count', 'date');

        $visitors = ArticleHistroy::selectRaw('DATE(read_at) as date, COUNT(DISTINCT COALESCE(user_id, session_id)) as count')
            ->where('read_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->pluck('count', 'date');

        $labels = [];
        $visData = [];
        $pvData = [];

        foreach ($days as $date) {
            $labels[] = Carbon::parse($date)->format('D');
            $visData[] = (int) ($visitors[$date] ?? 0);
            $pvData[] = (int) ($pageViews[$date] ?? 0);
        }

        return [
            'labels' => $labels,
            'visitors' => $visData,
            'page_views' => $pvData,
        ];
    }

    private function getMonthlyRevenue(): array
    {
        $earnings = app(MonetizationAnalyticsService::class)->getMonthlyEarnings();

        return [
            'labels' => array_column($earnings, 'label'),
            'ad_revenue' => array_map(
                fn (array $row) => (int) round(($row['ad_revenue_cents'] ?? 0) / 100),
                $earnings,
            ),
            'subscriptions' => array_map(
                fn (array $row) => (int) round(($row['subscription_revenue_cents'] ?? 0) / 100),
                $earnings,
            ),
        ];
    }

    private function getRecentArticles(): array
    {
        return Article::with('category:id,title,slug')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function (Article $a) {
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'status' => $a->status->value,
                    'statusLabel' => ucfirst($a->status->value),
                    'timeAgo' => $a->updated_at?->diffForHumans() ?? '',
                ];
            })
            ->toArray();
    }

    private function getTopArticles(): array
    {
        return Article::with('category:id,title,slug')
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->orderByDesc('views')
            ->limit(5)
            ->get()
            ->map(function (Article $a, int $index) {
                $slug = $a->category?->slug ?? 'general';
                $label = $a->category?->title ?? 'General';

                return [
                    'rank' => $index + 1,
                    'title' => $a->title,
                    'category' => $slug,
                    'categoryLabel' => $label,
                    'views' => $a->views,
                    'trend' => 'up',
                ];
            })
            ->toArray();
    }
}
