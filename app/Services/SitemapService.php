<?php

namespace App\Services;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\SeoPage;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Builds the general and Google News sitemaps.
 *
 * The set of "public URLs" is derived from the same published/active entity
 * definitions the SEO resolver uses (published articles, active categories,
 * authors with published articles, tags on published articles, and the
 * non-template seo_pages rows) so the sitemap can't diverge from what actually
 * resolves. `noindex` is respected using the same governing-row rule as
 * SeoResolverService::robotsFor (template row, or a per-category exact row that
 * overrides it) — a page excluded here is exactly one that would resolve to
 * `noindex,nofollow`.
 */
class SitemapService
{
    private const CACHE_GENERAL = 'sitemap:general:xml';

    private const CACHE_NEWS = 'sitemap:news:xml';

    private const TTL_GENERAL = 3600;      // 1 hour — content URLs change slowly.

    private const TTL_NEWS = 600;          // 10 minutes — the 48h window shifts constantly.

    private const NEWS_WINDOW_HOURS = 48;

    private const NEWS_MAX_URLS = 1000;    // Google News hard limit per file.

    public function __construct(
        private readonly SeoResolverService $seo,
        private readonly SiteSettingsService $siteSettings,
    ) {}

    public function generalXml(): string
    {
        return Cache::remember(self::CACHE_GENERAL, self::TTL_GENERAL, fn () => $this->buildGeneral()->render());
    }

    public function newsXml(): string
    {
        return Cache::remember(self::CACHE_NEWS, self::TTL_NEWS, fn () => $this->buildNews()->render());
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_GENERAL);
        Cache::forget(self::CACHE_NEWS);
    }

    private function buildGeneral(): Sitemap
    {
        $byKey = $this->seoPagesByKey();
        $byPath = $this->seoPagesByPath();
        $sitemap = Sitemap::create();

        // Home
        if (! $this->isNoindex($byKey->get('home'))) {
            $sitemap->add(Url::create($this->seo->frontendUrl('/'))->setLastModificationDate(now()));
        }

        // Static content pages: non-template seo_pages rows that aren't home,
        // the news-details landing, or a per-category row.
        foreach ($this->staticPages() as $row) {
            if ($this->isNoindex($row)) {
                continue;
            }
            $sitemap->add(
                Url::create($this->seo->frontendUrl($row->url_path))
                    ->setLastModificationDate($row->updated_at ?? now())
            );
        }

        // Categories (active) — governed by a per-category exact row, else the template.
        foreach ($this->activeCategories() as $category) {
            $govern = $byPath->get('/'.$category->slug) ?? $byKey->get('category');
            if ($this->isNoindex($govern)) {
                continue;
            }
            $sitemap->add(
                Url::create($this->seo->frontendUrl('/'.$category->slug))
                    ->setLastModificationDate($category->updated_at ?? now())
            );
        }

        // Articles — governed by the article-detail template.
        if (! $this->isNoindex($byKey->get('article-detail'))) {
            foreach ($this->publishedArticles() as $article) {
                $sitemap->add(
                    Url::create($this->seo->frontendUrl('/'.$article->slug))
                        ->setLastModificationDate($article->updated_at ?? $article->published_at ?? now())
                );
            }
        }

        // Author profiles — governed by the author-profile template.
        if (! $this->isNoindex($byKey->get('author-profile'))) {
            foreach ($this->publishedAuthors() as $author) {
                $sitemap->add(
                    Url::create($this->seo->frontendUrl('/author/'.$author->slug))
                        ->setLastModificationDate($author->updated_at ?? now())
                );
            }
        }

        // Tag pages — governed by the tag template.
        if (! $this->isNoindex($byKey->get('tag'))) {
            foreach ($this->publishedTags() as $tag) {
                $slug = $this->tagSlug((string) $tag->tag);
                if ($slug === '') {
                    continue;
                }
                $sitemap->add(
                    Url::create($this->seo->frontendUrl('/tag/'.$slug))
                        ->setLastModificationDate($tag->updated_at ?? now())
                );
            }
        }

        return $sitemap;
    }

    private function buildNews(): Sitemap
    {
        $sitemap = Sitemap::create();

        // If the article template is noindex, no article should be submitted at all.
        if ($this->isNoindex($this->seoPagesByKey()->get('article-detail'))) {
            return $sitemap;
        }

        $settings = $this->siteSettings->getOrDefault();
        $name = trim((string) $settings->site_name) ?: 'ZBC News';
        $language = trim((string) $settings->language) ?: 'en';

        foreach ($this->recentArticles() as $article) {
            $sitemap->add(
                Url::create($this->seo->frontendUrl('/'.$article->slug))
                    ->addNews($name, $language, (string) $article->title, $article->published_at)
            );
        }

        return $sitemap;
    }

    /**
     * @return Collection<int, Article>
     */
    private function publishedArticles()
    {
        return Article::query()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->orderByDesc('published_at')
            ->get(['id', 'slug', 'updated_at', 'published_at']);
    }

    /**
     * @return Collection<int, Article>
     */
    private function recentArticles()
    {
        return Article::query()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subHours(self::NEWS_WINDOW_HOURS))
            ->orderByDesc('published_at')
            ->limit(self::NEWS_MAX_URLS)
            ->get(['id', 'slug', 'title', 'published_at']);
    }

    /**
     * @return Collection<int, ArticleCategory>
     */
    private function activeCategories()
    {
        return ArticleCategory::query()
            ->where('status', ArticleCategoryStatus::ACTIVE->value)
            ->orderBy('id')
            ->get(['id', 'slug', 'updated_at']);
    }

    /**
     * @return Collection<int, User>
     */
    private function publishedAuthors()
    {
        $authorIds = Article::query()
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->distinct()
            ->pluck('user_id')
            ->filter();

        return User::query()
            ->whereIn('id', $authorIds)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get(['id', 'slug', 'updated_at']);
    }

    /**
     * @return Collection<int, Tag>
     */
    private function publishedTags()
    {
        return Tag::query()
            ->whereHas('articles', fn ($q) => $q->where('status', ArticleStatus::PUBLISHED->value))
            ->get(['id', 'tag', 'updated_at']);
    }

    /**
     * @return Collection<int, SeoPage>
     */
    private function staticPages()
    {
        return SeoPage::query()
            ->where('is_template', false)
            ->whereNotIn('page_key', ['home', 'news-details'])
            ->where('page_key', 'not like', 'category-%')
            ->get();
    }

    /**
     * @return Collection<string, SeoPage>
     */
    private function seoPagesByKey()
    {
        return SeoPage::query()->get()->keyBy('page_key');
    }

    /**
     * @return Collection<string, SeoPage>
     */
    private function seoPagesByPath()
    {
        return SeoPage::query()->where('is_template', false)->get()->keyBy('url_path');
    }

    private function isNoindex(?SeoPage $row): bool
    {
        return $row !== null && (bool) $row->noindex;
    }

    /**
     * Slugify a tag exactly as the frontend slugifyTag() does: trim, drop a
     * leading '#', lowercase, collapse whitespace/underscores to hyphens.
     */
    private function tagSlug(string $tag): string
    {
        $value = preg_replace('/^#/', '', trim($tag)) ?? '';
        $value = mb_strtolower($value);

        return preg_replace('/[\s_]+/', '-', $value) ?? '';
    }

    /** Exposed for the scheduled/warm command and tests. */
    public function newsWindowStart(): Carbon
    {
        return now()->subHours(self::NEWS_WINDOW_HOURS);
    }
}
