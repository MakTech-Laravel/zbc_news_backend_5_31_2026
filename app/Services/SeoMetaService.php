<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleCategory;

class SeoMetaService
{
    public function __construct(
        private readonly SiteSettingsService $siteSettingsService,
    ) {}

    public function siteName(): string
    {
        return $this->siteSettingsService->getOrDefault()->site_name ?: 'ZBC News';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $tags
     * @return array<string, mixed>
     */
    public function applyArticleMeta(array $data, array $tags = [], ?string $categoryTitle = null): array
    {
        $siteName = $this->siteName();
        $title = trim((string) ($data['title'] ?? ''));

        if (empty(trim((string) ($data['meta_title'] ?? ''))) && $title !== '') {
            $data['meta_title'] = $this->truncate("{$title} — {$siteName}", 255);
        }

        if (empty(trim((string) ($data['meta_description'] ?? '')))) {
            $description = trim((string) ($data['excerpt'] ?? ''));
            if ($description === '') {
                $description = $this->plainText((string) ($data['article_description'] ?? ''));
            }
            $data['meta_description'] = $this->truncate($description, 500);
        }

        if (empty(trim((string) ($data['meta_keywords'] ?? '')))) {
            $keywords = array_filter(array_unique(array_merge(
                $tags,
                $categoryTitle ? [$categoryTitle] : [],
                ['news'],
            )));
            $data['meta_keywords'] = $this->truncate(implode(', ', $keywords), 500);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyCategoryMeta(array $data): array
    {
        $siteName = $this->siteName();
        $title = trim((string) ($data['title'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));

        if (empty(trim((string) ($data['meta_title'] ?? ''))) && $title !== '') {
            $data['meta_title'] = $this->truncate("{$title} News — {$siteName}", 255);
        }

        if (empty(trim((string) ($data['meta_description'] ?? ''))) && $title !== '') {
            $data['meta_description'] = $this->truncate(
                "Latest {$title} news, analysis, and updates from {$siteName}.",
                500,
            );
        }

        if (empty(trim((string) ($data['meta_keywords'] ?? '')))) {
            $keywords = array_filter([$slug, strtolower($title), 'news', 'articles']);
            $data['meta_keywords'] = $this->truncate(implode(', ', array_unique($keywords)), 500);
        }

        return $data;
    }

    public function resolveArticleMeta(Article $article): array
    {
        $tags = $article->relationLoaded('tags')
            ? $article->tags->pluck('tag')->all()
            : $article->tags()->pluck('tag')->all();

        $categoryTitle = $article->relationLoaded('category')
            ? $article->category?->title
            : $article->category()->value('title');

        return [
            'meta_title'       => $article->meta_title,
            'meta_description' => $article->meta_description,
            'meta_keywords'    => $article->meta_keywords,
            'resolved'         => $this->applyArticleMeta([
                'title'                => $article->title,
                'excerpt'              => $article->excerpt,
                'article_description'  => $article->article_description,
                'meta_title'           => $article->meta_title,
                'meta_description'     => $article->meta_description,
                'meta_keywords'        => $article->meta_keywords,
            ], $tags, $categoryTitle),
        ];
    }

    public function resolveCategoryMeta(ArticleCategory $category): array
    {
        return [
            'meta_title'       => $category->meta_title,
            'meta_description' => $category->meta_description,
            'meta_keywords'    => $category->meta_keywords,
            'resolved'         => $this->applyCategoryMeta([
                'title'              => $category->title,
                'slug'               => $category->slug,
                'meta_title'         => $category->meta_title,
                'meta_description'   => $category->meta_description,
                'meta_keywords'      => $category->meta_keywords,
            ]),
        ];
    }

    private function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    }

    private function truncate(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $max - 1)) . '…';
    }
}
