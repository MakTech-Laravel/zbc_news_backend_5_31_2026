<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleHistroy;
use App\Models\Tag;
use Carbon\Carbon;

class UserDashboardService
{
    public function getDashboard(int $userId): array
    {
        return [
            'featured_story'    => $this->getFeaturedStory(),
            'feeds'             => $this->getFeeds(),
            'continue_reading'  => $this->getContinueReading($userId),
            'trending_topics'   => $this->getTrendingTopics(),
            'this_week'         => $this->getThisWeek($userId),
        ];
    }

    private function getFeaturedStory(): ?array
    {
        $article = Article::with(['category:id,name,slug,title', 'tags:id,tag'])
            ->where('status', ArticleStatus::Published)
            ->orderByDesc('views')
            ->first();

        if (! $article) {
            return null;
        }

        $categoryLabel = $article->category?->title ?? $article->category?->name ?? 'General';

        return [
            'id'        => $article->id,
            'title'     => $article->title,
            'slug'      => $article->slug,
            'excerpt'   => $article->excerpt ?? '',
            'imageUrl'  => $article->featured_image ?? '',
            'category'  => $categoryLabel,
            'readTime'  => $this->estimateReadTime($article->article_description),
            'views'     => (int) $article->views,
        ];
    }

    private function getFeeds(): array
    {
        $recommended = Article::with(['category:id,name,slug,title'])
            ->where('status', ArticleStatus::Published)
            ->orderByDesc('views')
            ->limit(6)
            ->get()
            ->map(fn($a) => $this->mapFeedArticle($a))
            ->values()
            ->toArray();

        $latest = Article::with(['category:id,name,slug,title'])
            ->where('status', ArticleStatus::Published)
            ->orderByDesc('published_at')
            ->limit(6)
            ->get()
            ->map(fn($a) => $this->mapFeedArticle($a))
            ->values()
            ->toArray();

        $trending = Article::with(['category:id,name,slug,title'])
            ->where('status', ArticleStatus::Published)
            ->orderByDesc('views')
            ->offset(6)
            ->limit(6)
            ->get()
            ->map(fn($a) => $this->mapFeedArticle($a))
            ->values()
            ->toArray();

        return [
            'recommended' => $recommended,
            'latest'      => $latest,
            'trending'    => $trending,
        ];
    }

    private function mapFeedArticle(Article $a): array
    {
        $categoryLabel = $a->category?->title ?? $a->category?->name ?? 'General';

        return [
            'id'          => $a->id,
            'title'       => $a->title,
            'slug'        => $a->slug,
            'excerpt'     => $a->excerpt ?? '',
            'imageUrl'    => $a->featured_image ?? '',
            'category'    => $categoryLabel,
            'categorySlug'=> $a->category?->slug ?? 'general',
            'readTime'    => $this->estimateReadTime($a->article_description),
            'views'       => (int) $a->views,
            'publishedAt' => $a->published_at?->diffForHumans() ?? '',
        ];
    }

    private function getContinueReading(int $userId): array
    {
        $histories = ArticleHistroy::with([
                'article:id,title,slug,featured_image,article_description,article_category_id,published_at',
                'article.category:id,name,slug,title',
            ])
            ->where('user_id', $userId)
            ->where('is_guest', false)
            ->orderByDesc('read_at')
            ->limit(3)
            ->get();

        return $histories->map(function (ArticleHistroy $h) {
            $a = $h->article;
            if (! $a) {
                return null;
            }
            $categoryLabel = $a->category?->title ?? $a->category?->name ?? 'General';
            return [
                'id'          => $a->id,
                'title'       => $a->title,
                'slug'        => $a->slug,
                'category'    => $categoryLabel,
                'readTime'    => $this->estimateReadTime($a->article_description),
                'publishedAt' => $a->published_at?->diffForHumans() ?? '',
            ];
        })->filter()->values()->toArray();
    }

    private function getTrendingTopics(): array
    {
        return Tag::withCount('articles')
            ->orderByDesc('articles_count')
            ->limit(8)
            ->get()
            ->map(function (Tag $tag, int $index) {
                return [
                    'id'    => $tag->id,
                    'rank'  => $index + 1,
                    'label' => $tag->tag,
                    'slug'  => $tag->slug ?? \Illuminate\Support\Str::slug($tag->tag),
                    'count' => $tag->articles_count,
                ];
            })
            ->toArray();
    }

    private function getThisWeek(int $userId): array
    {
        $weekStart = now()->startOfWeek();

        $articlesRead = ArticleHistroy::where('user_id', $userId)
            ->where('is_guest', false)
            ->where('read_at', '>=', $weekStart)
            ->count();

        $readingSeconds = ArticleHistroy::where('user_id', $userId)
            ->where('is_guest', false)
            ->where('read_at', '>=', $weekStart)
            ->sum('time_spent');

        $readingMinutes = (int) round($readingSeconds / 60);
        $readingDisplay = $readingMinutes >= 60
            ? round($readingMinutes / 60, 1) . ' hrs'
            : $readingMinutes . ' min';

        // Progress normalised to reasonable weekly targets
        $articleTarget  = 20;
        $minuteTarget   = 120;

        return [
            'articlesRead' => [
                'value'    => $articlesRead,
                'progress' => min(100, (int) round(($articlesRead / $articleTarget) * 100)),
            ],
            'readingTime'  => [
                'value'    => $readingDisplay,
                'progress' => min(100, (int) round(($readingMinutes / $minuteTarget) * 100)),
            ],
        ];
    }

    private function estimateReadTime(?string $content): string
    {
        if (! $content) {
            return '3 min read';
        }
        $wordCount = str_word_count(strip_tags($content));
        $minutes   = max(1, (int) round($wordCount / 200));
        return $minutes . ' min read';
    }
}
