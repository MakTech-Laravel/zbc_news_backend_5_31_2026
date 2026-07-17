<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Category;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    /**
     * The cached value is the resolved resource payload (a plain array), never the Eloquent
     * collection: cache stores serialize their payloads, and models do not survive that round
     * trip here — they come back as __PHP_Incomplete_Class and blow up. Caching the array also
     * skips the per-category SEO resolution the resource performs on every warm hit.
     */
    public function index()
    {
        $categories = Cache::remember(
            CategoryService::CACHE_PUBLIC,
            CategoryService::TTL_PUBLIC,
            fn () => Category::collection($this->categoryService->getAllCategories())
                ->resolve(),
        );

        return sendResponse(
            true,
            'Categories retrieved successfully',
            $categories,
            HttpStatus::HTTP_OK,
        );
    }
}
