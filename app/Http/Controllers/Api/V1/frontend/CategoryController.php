<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
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

}
