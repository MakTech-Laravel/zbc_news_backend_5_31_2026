<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\SeoPage;
use App\Models\SiteSettings;
use App\Models\User;
use App\Support\MediaUrl;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * Resolves the fully-interpolated SEO metadata (title, description, keywords,
 * canonical, robots, Open Graph, Twitter, JSON-LD) for a public URL path.
 *
 * The backend owns the article/category/author data, so all placeholder
 * interpolation and entity disambiguation happen here — the client never sees
 * a raw {token}. The contract is intentionally stateless-by-path so a future
 * SSR loader can call it with `new URL(request.url).pathname`.
 */
class SeoResolverService
{
    /** Google drops NewsArticle.headline values longer than this. */
    private const HEADLINE_MAX = 110;

    /**
     * Single-segment paths that must never be treated as an article/category slug.
     * Mirrors RESERVED_SINGLE_SEGMENT_PATHS in the frontend useDocumentHead hook.
     *
     * @var array<int, string>
     */
    private const RESERVED_SEGMENTS = [
        'login', 'register', 'forget-password', 'otp-verification', 'reset-password',
        'unauthorized', 'dashboard', 'ws-test', 'about', 'contact', 'privacy', 'terms',
        'cookie-policy', 'accessibility-statement', 'advertise', 'careers', 'newsletter',
        'admin', 'user', 'news-details',
    ];

    public function __construct(
        private readonly ArticleService $articles,
        private readonly SiteSettingsService $siteSettings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $path): array
    {
        $normalized = $this->normalizePath($path);
        $settings = $this->siteSettings->getOrDefault();
        $segments = array_values(array_filter(explode('/', $normalized), fn ($s) => $s !== ''));

        // /author/{slug}
        if (count($segments) === 2 && $segments[0] === 'author') {
            return $this->buildForAuthor($segments[1], $settings);
        }

        // /tag/{slug}
        if (count($segments) === 2 && $segments[0] === 'tag') {
            return $this->buildForTag($segments[1], $settings);
        }

        // Article/category slug: real route is /{slug}; /news-details/{slug} is the legacy alias.
        $slug = null;
        if (count($segments) === 1 && ! in_array($segments[0], self::RESERVED_SEGMENTS, true)) {
            $slug = $segments[0];
        } elseif (count($segments) === 2 && $segments[0] === 'news-details') {
            $slug = $segments[1];
        }

        if ($slug !== null) {
            // Article wins over category — matches Home.tsx:43-51 live fallback order.
            $article = $this->findArticle($slug);
            if ($article) {
                return $this->buildForArticle($article, $settings);
            }

            $category = ArticleCategory::where('slug', $slug)->first();
            if ($category) {
                return $this->buildForCategory($category, $settings);
            }
        }

        return $this->buildForStatic($normalized, $settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildForArticle(Article $article, SiteSettings $settings): array
    {
        $template = $this->templateByKey('article-detail');
        $tags = $article->relationLoaded('tags') ? $article->tags->pluck('tag')->filter()->all() : [];
        $categoryTitle = (string) ($article->category?->title ?? '');

        $repl = [
            'title' => (string) ($article->title ?? ''),
            'excerpt' => $this->plainExcerpt($article),
            'category' => $categoryTitle,
            'tags' => implode(', ', $tags),
            'author' => (string) ($article->user?->name ?? ''),
        ];

        // Fallback chain: raw entity meta -> interpolated template -> site meta -> site name/tag.
        $title = $this->pick($article->meta_title, $this->interpolate($template?->meta_title, $repl), $settings->meta_title, $this->siteName($settings));
        $description = $this->pick($article->meta_description, $this->interpolate($template?->meta_description, $repl), $settings->meta_description, $this->siteTag($settings));
        $keywords = $this->pick($article->meta_keywords, $this->interpolate($template?->meta_keywords, $repl), $settings->meta_keywords);

        $image = MediaUrl::resolvePublic($article->open_graph_image ?: $article->featured_image)
            ?: MediaUrl::resolvePublic($template?->og_image)
            ?: $this->siteDefaultImage($settings);
        $published = $this->iso8601($article->published_at);
        $modified = $this->iso8601($article->updated_at);
        $canonical = $this->frontendUrl('/'.$article->slug);

        return $this->assemble([
            'page_key' => 'article-detail',
            'matched_entity' => 'article',
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $canonical,
            'robots' => $this->robotsFor($template),
            'og_type' => 'article',
            'image' => $image,
            'published_time' => $published,
            'modified_time' => $modified,
            'json_ld' => [$this->newsArticleJsonLd($article, $description, $image, $published, $modified, $keywords, $settings)],
        ], $settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildForCategory(ArticleCategory $category, SiteSettings $settings): array
    {
        $template = $this->templateByKey('category');
        // A per-category non-template row (e.g. /business) can override canonical/noindex.
        $exact = SeoPage::where('url_path', '/'.$category->slug)->where('is_template', false)->first();
        $govern = $exact ?? $template;
        $repl = ['category' => (string) ($category->title ?? '')];

        $title = $this->pick($category->meta_title, $this->interpolate($template?->meta_title, $repl), $settings->meta_title, $this->siteName($settings));
        $description = $this->pick($category->meta_description, $this->interpolate($template?->meta_description, $repl), $settings->meta_description, $this->siteTag($settings));
        $keywords = $this->pick($category->meta_keywords, $this->interpolate($template?->meta_keywords, $repl), $settings->meta_keywords);

        return $this->assemble([
            'page_key' => 'category',
            'matched_entity' => 'category',
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $this->pick($exact?->canonical_url, $this->frontendUrl('/'.$category->slug)),
            'robots' => $this->robotsFor($govern),
            'og_type' => 'website',
            'image' => MediaUrl::resolvePublic($govern?->og_image) ?: $this->siteDefaultImage($settings),
            'json_ld' => [],
        ], $settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildForAuthor(string $slug, SiteSettings $settings): array
    {
        $author = User::where('slug', $slug)->with('userInformation')->first();
        $template = $this->templateByKey('author-profile');
        $name = $author?->name ?: $this->humanize($slug);
        $bio = (string) ($author?->userInformation?->bio ?? '');

        $repl = ['author' => $name, 'bio' => $bio];

        $title = $this->pick($this->interpolate($template?->meta_title, $repl), $settings->meta_title, $this->siteName($settings));
        $description = $this->pick($this->interpolate($template?->meta_description, $repl), $bio, $settings->meta_description, $this->siteTag($settings));
        $keywords = $this->pick($this->interpolate($template?->meta_keywords, $repl), $settings->meta_keywords);

        $image = MediaUrl::resolvePublic($author?->userInformation?->profile_image)
            ?: MediaUrl::resolvePublic($template?->og_image)
            ?: $this->siteDefaultImage($settings);

        return $this->assemble([
            'page_key' => 'author-profile',
            'matched_entity' => 'author',
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $this->frontendUrl('/author/'.$slug),
            'robots' => $this->robotsFor($template),
            'og_type' => 'profile',
            'image' => $image,
            'json_ld' => [],
        ], $settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildForTag(string $slug, SiteSettings $settings): array
    {
        $template = $this->templateByKey('tag');
        $label = $this->humanize($slug);
        $repl = ['tag' => $label];

        $title = $this->pick($this->interpolate($template?->meta_title, $repl), $settings->meta_title, $this->siteName($settings));
        $description = $this->pick($this->interpolate($template?->meta_description, $repl), $settings->meta_description, $this->siteTag($settings));
        $keywords = $this->pick($this->interpolate($template?->meta_keywords, $repl), $settings->meta_keywords);

        return $this->assemble([
            'page_key' => 'tag',
            'matched_entity' => 'tag',
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $this->frontendUrl('/tag/'.$slug),
            'robots' => $this->robotsFor($template),
            'og_type' => 'website',
            'image' => MediaUrl::resolvePublic($template?->og_image) ?: $this->siteDefaultImage($settings),
            'json_ld' => [],
        ], $settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildForStatic(string $path, SiteSettings $settings): array
    {
        $seoPage = SeoPage::where('url_path', $path)->where('is_template', false)->first();
        if (! $seoPage && $path === '/') {
            $seoPage = $this->templateByKey('home');
        }

        $title = $this->pick($this->interpolate($seoPage?->meta_title, []), $settings->meta_title, $this->siteName($settings));
        $description = $this->pick($this->interpolate($seoPage?->meta_description, []), $settings->meta_description, $this->siteTag($settings));
        $keywords = $this->pick($this->interpolate($seoPage?->meta_keywords, []), $settings->meta_keywords);

        return $this->assemble([
            'page_key' => $seoPage?->page_key ?? 'home',
            'matched_entity' => 'static',
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $this->pick($seoPage?->canonical_url, $this->frontendUrl($path)),
            'robots' => $this->robotsFor($seoPage),
            'og_type' => 'website',
            'image' => MediaUrl::resolvePublic($seoPage?->og_image) ?: $this->siteDefaultImage($settings),
            'json_ld' => $path === '/' ? $this->homeJsonLd($settings) : [],
        ], $settings);
    }

    /**
     * Assemble the shared OG/Twitter/envelope shape from resolved primitives.
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function assemble(array $r, SiteSettings $settings): array
    {
        $image = $r['image'] ?? null;
        $notEmpty = fn ($v) => $v !== null && $v !== '';

        $og = array_filter([
            'title' => $r['title'],
            'description' => $r['description'],
            'type' => $r['og_type'] ?? 'website',
            'url' => $r['canonical'],
            'site_name' => $this->siteName($settings),
            'image' => $image,
            'image_alt' => $image ? $r['title'] : null,
            'published_time' => $r['published_time'] ?? null,
            'modified_time' => $r['modified_time'] ?? null,
        ], $notEmpty);

        $twitter = array_filter([
            'card' => $image ? 'summary_large_image' : 'summary',
            'title' => $r['title'],
            'description' => $r['description'],
            'image' => $image,
            'image_alt' => $image ? $r['title'] : null,
        ], $notEmpty);

        return [
            'page_key' => $r['page_key'],
            'matched_entity' => $r['matched_entity'],
            'title' => $r['title'],
            'description' => $r['description'],
            'keywords' => $this->cleanKeywords($r['keywords']),
            'canonical' => $r['canonical'],
            'robots' => $r['robots'] ?? 'index,follow',
            'og' => $og,
            'twitter' => $twitter,
            'json_ld' => array_values(array_filter($r['json_ld'] ?? [])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function newsArticleJsonLd(
        Article $article,
        string $description,
        ?string $image,
        ?string $published,
        ?string $modified,
        string $keywords,
        SiteSettings $settings,
    ): array {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $this->headline((string) ($article->title ?? '')),
            'description' => $description,
            'mainEntityOfPage' => $this->frontendUrl('/'.$article->slug),
            'isAccessibleForFree' => true,
            'publisher' => $this->organization($settings),
        ];

        if ($image) {
            $data['image'] = [$image];
        }
        if ($published) {
            $data['datePublished'] = $published;
        }
        if ($modified) {
            $data['dateModified'] = $modified;
        }
        if ($article->category?->title) {
            $data['articleSection'] = $article->category->title;
        }
        $keywords = $this->cleanKeywords($keywords);
        if ($keywords !== '') {
            $data['keywords'] = $keywords;
        }
        if ($author = $article->user) {
            $data['author'] = array_filter([
                '@type' => 'Person',
                'name' => $author->name,
                'url' => $author->slug ? $this->frontendUrl('/author/'.$author->slug) : null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function homeJsonLd(SiteSettings $settings): array
    {
        return [
            array_merge(['@context' => 'https://schema.org'], $this->organization($settings)),
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $this->siteName($settings),
                'url' => $this->frontendUrl('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => $this->frontendUrl('/search?q={search_term_string}'),
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organization(SiteSettings $settings): array
    {
        $org = [
            '@type' => 'Organization',
            'name' => $this->siteName($settings),
            'url' => $this->frontendUrl('/'),
        ];

        $logo = MediaUrl::resolvePublic($settings->site_logo);
        if ($logo) {
            $org['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
        }

        return $org;
    }

    /**
     * Truncate a headline to <= 110 chars on a word boundary, appending an ellipsis.
     */
    private function headline(string $value): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= self::HEADLINE_MAX) {
            return $value;
        }

        $truncated = mb_substr($value, 0, self::HEADLINE_MAX - 1);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > self::HEADLINE_MAX / 2) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated).'…';
    }

    /**
     * Normalize any datetime to an ISO 8601 string that carries a timezone offset,
     * or null if it cannot be parsed into one. Never emits a naive/floating datetime.
     */
    private function iso8601(mixed $value): ?string
    {
        if (! $value instanceof CarbonInterface) {
            if (! is_string($value) || trim($value) === '') {
                return null;
            }
            try {
                $value = Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        $iso = $value->toIso8601String();

        return preg_match('/T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/', $iso) === 1 ? $iso : null;
    }

    private function findArticle(string $slug): ?Article
    {
        try {
            return $this->articles->getPublishedBySlug($slug);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    private function templateByKey(string $key): ?SeoPage
    {
        return SeoPage::where('page_key', $key)->first();
    }

    private function robotsFor(?SeoPage $row): string
    {
        return $row && $row->noindex ? 'noindex,nofollow' : 'index,follow';
    }

    /**
     * Deepest social-image fallback: site logo, then a configured default OG
     * asset, then null (image-less pages omit og:image and use a summary card).
     */
    private function siteDefaultImage(SiteSettings $settings): ?string
    {
        return MediaUrl::resolvePublic($settings->site_logo)
            ?: MediaUrl::resolvePublic((string) config('app.og_default_image'))
            ?: null;
    }

    /**
     * First non-empty candidate after trimming; '' if none.
     */
    private function pick(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $trimmed = trim((string) $candidate);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return '';
    }

    /**
     * Replace {token} placeholders. If any placeholder is left unresolved, the
     * template is considered unusable and '' is returned so the fallback chain
     * continues (mirrors hasUnresolvedPlaceholders in the frontend).
     *
     * @param  array<string, string>  $replacements
     */
    private function interpolate(?string $template, array $replacements): string
    {
        $value = (string) $template;
        if ($value === '') {
            return '';
        }

        foreach ($replacements as $key => $replacement) {
            $value = str_replace(['{'.$key.'}', '{'.strtolower($key).'}'], $replacement, $value);
        }

        if (preg_match('/\{[a-zA-Z_]+\}/', $value) === 1) {
            return '';
        }

        return trim($value);
    }

    private function plainExcerpt(Article $article): string
    {
        $excerpt = trim((string) ($article->excerpt ?? ''));
        if ($excerpt === '') {
            $excerpt = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $article->article_description)));
        }

        return mb_strlen($excerpt) > 300 ? rtrim(mb_substr($excerpt, 0, 299)).'…' : $excerpt;
    }

    private function humanize(string $slug): string
    {
        return ucwords(trim(str_replace('-', ' ', $slug)));
    }

    /**
     * Normalize a comma-separated keyword list: trim each term and drop empties,
     * so an empty {token} interpolation cannot leave a dangling separator.
     */
    private function cleanKeywords(string $value): string
    {
        $parts = array_filter(
            array_map('trim', explode(',', $value)),
            fn (string $part) => $part !== '',
        );

        return implode(', ', $parts);
    }

    private function siteName(SiteSettings $settings): string
    {
        return trim((string) $settings->site_name) ?: 'ZBC News';
    }

    private function siteTag(SiteSettings $settings): string
    {
        return trim((string) $settings->site_tag) ?: 'Breaking news and analysis from around the world';
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        if ($path === '' || $path === '/') {
            return $base.'/';
        }

        return $base.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    private function normalizePath(string $path): string
    {
        return '/'.trim($path, '/');
    }
}
