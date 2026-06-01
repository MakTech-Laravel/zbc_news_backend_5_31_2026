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

        return $category->fresh();
    }
    
    public function delete(string $slug): void
    {
        $category = $this->getBySlug($slug);
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
