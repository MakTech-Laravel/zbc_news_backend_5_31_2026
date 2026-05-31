<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
     public function __construct(
       private readonly CategoryService $categoryService
    ) {}
    

    public function index()
    {
        $categories = $this->categoryService->getAllCategories();
        
        return Category::collection($categories);
    }

    public function store(Request $request)
    {
        $category = $this->categoryService->createCategory($request->all());
        
        return Category::make($category);
    }

    
}
