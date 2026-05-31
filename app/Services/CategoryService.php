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
    
    public function createCategory(array $data)
    {
        return ArticleCategory::create($data);
    }
}
