<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Category;
use App\Services\CategoryService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    public function index()
    {
        $categories = $this->categoryService->getCategoryTree();

        return sendResponse(
            true,
            'Categories retrieved successfully',
            Category::collection($categories),
            HttpStatus::HTTP_OK,
        );
    }
}
