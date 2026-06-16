<?php

namespace App\Services;

use App\Models\ArticleCategory;
use App\Models\SeoPage;

class CategoryService
{
    public function __construct(
        private readonly SeoMetaService $seoMetaService,
    ) {}

    public function getAllCategories()
    {
        return ArticleCategory::all();
    }

    public function create(array $data): ArticleCategory
    {
        $data = $this->seoMetaService->applyCategoryMeta($data);

        $category = ArticleCategory::create($data);
        $this->syncSeoPage($category);

        return $category;
    }

    public function getBySlug(string $slug): ArticleCategory
    {
        return ArticleCategory::where('slug', $slug)->firstOrFail();
    }

    public function update(ArticleCategory $category, array $data): ArticleCategory
    {
        $data = $this->seoMetaService->applyCategoryMeta($data);
        $category->update($data);
        $category->refresh();
        $this->syncSeoPage($category);

        return $category;
    }

    public function delete(string $slug): void
    {
        $category = $this->getBySlug($slug);

        if ($category->articles()->exists()) {
            throw new \Exception('Cannot delete category with existing articles.');
        }

        $category->delete();
    }

    public function restore(string $slug): ArticleCategory
    {
        $category = ArticleCategory::withTrashed()
            ->where('slug', $slug)
            ->firstOrFail();

        $category->restore();

        return $category;
    }

    public function forceDelete(string $slug): void
    {
        $category = ArticleCategory::withTrashed()
            ->where('slug', $slug)
            ->firstOrFail();

        $category->forceDelete();
    }

    private function syncSeoPage(ArticleCategory $category): void
    {
        SeoPage::updateOrCreate(
            ['page_key' => 'category-' . $category->slug],
            [
                'name'             => $category->title . ' Category',
                'url_path'         => '/' . $category->slug,
                'is_template'      => false,
                'meta_title'       => $category->meta_title,
                'meta_description' => $category->meta_description,
                'meta_keywords'    => $category->meta_keywords,
            ],
        );
    }
}
