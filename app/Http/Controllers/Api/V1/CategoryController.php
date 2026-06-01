<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}


    public function index()
    {
        $categories = $this->categoryService->getAllCategories();

        return sendResponse(
            true,
            'Categories retrieved successfully',
            $categories,
            HttpStatus::HTTP_OK,
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:article_categories,slug',
            'parent_id' => 'nullable|integer|exists:article_categories,id',
        ]);

        $category = $this->categoryService->create($validated);


        return sendResponse(
            true,
            'Category created successfully',
            new Category($category),
            HttpStatus::HTTP_CREATED,
        );
    }
}
