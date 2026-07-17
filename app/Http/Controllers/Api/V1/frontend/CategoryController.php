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
     * The cached value is the resource payload round-tripped through JSON, which is what makes
     * it cache-safe.
     *
     * resolve() alone is not enough: it resolves only the top level, so nested values stay as
     * live objects — `children` comes back an Eloquent Collection (map() on an Eloquent
     * collection returns one), and `status` a backed enum. Those serialize into the cache but
     * unserialize in a fresh worker as __PHP_Incomplete_Class, and the endpoint then serves
     * `{"__PHP_Incomplete_Class_Name": ...}` in place of every category's children — a 200
     * response carrying corrupt data rather than a visible failure.
     *
     * Encoding and decoding forces every nested object (collections, enums, Carbon dates)
     * through its JSON representation, which is exactly what the response returns anyway, so
     * the cached value is identical to the uncached one and contains no class references at
     * all. Caching it also skips the per-category SEO resolution on warm hits.
     */
    public function index()
    {
        $categories = Cache::remember(
            CategoryService::CACHE_PUBLIC,
            CategoryService::TTL_PUBLIC,
            fn () => json_decode(
                Category::collection($this->categoryService->getAllCategories())->toJson(),
                true,
            ),
        );

        return sendResponse(
            true,
            'Categories retrieved successfully',
            $categories,
            HttpStatus::HTTP_OK,
        );
    }
}
