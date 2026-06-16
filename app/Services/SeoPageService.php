<?php

namespace App\Services;

use App\Models\SeoPage;
use Illuminate\Database\Eloquent\Collection;

class SeoPageService
{
    public function __construct(
        private readonly SeoPage $seoPage
    ) {}

    public function getAll(): Collection
    {
        return $this->seoPage->orderBy('name')->get();
    }

    public function getByPageKey(string $pageKey): SeoPage
    {
        return $this->seoPage->where('page_key', $pageKey)->firstOrFail();
    }

    public function update(string $pageKey, array $data): SeoPage
    {
        $page = $this->getByPageKey($pageKey);
        $page->update($data);

        return $page->fresh();
    }

    public function resolveForPath(string $path): ?SeoPage
    {
        $normalized = '/' . trim($path, '/');
        if ($normalized === '/') {
            $normalized = '/';
        }

        $exact = $this->seoPage->where('url_path', $normalized)->where('is_template', false)->first();
        if ($exact) {
            return $exact;
        }

        if (preg_match('#^/news-details/[^/]+$#', $normalized)) {
            return $this->seoPage->where('page_key', 'article-detail')->first();
        }

        if ($normalized === '/news-details') {
            return $this->seoPage->where('page_key', 'news-details')->first();
        }

        if (preg_match('#^/[^/]+$#', $normalized)) {
            return $this->seoPage->where('page_key', 'category')->first();
        }

        return $this->seoPage->where('page_key', 'home')->first();
    }
}
