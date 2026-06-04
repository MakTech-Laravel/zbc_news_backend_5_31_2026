<?php

namespace App\Services;

use App\Models\ArticleCategory;

class CategoryService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function getAllCategories()
    {
        return ArticleCategory::all();
    }

    public function create(array $data): ArticleCategory
    {
        return ArticleCategory::create($data);
    }


    public function getBySlug(string $slug): ArticleCategory
    {
        return ArticleCategory::where('slug', $slug)->first();
    }

    public function update(ArticleCategory $category, array $data): ArticleCategory
    {
        $category->update($data);
        $category->refresh();
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
}
