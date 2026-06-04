<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ArticleCategoryStatus;
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
            'status' => 'required|string|in:' . implode(',', array_column(ArticleCategoryStatus::options(), 'value')),
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

    public function show(string $slug)
    {
        $category = $this->categoryService->getBySlug($slug);

        return sendResponse(
            true,
            'Category retrieved successfully',
            new Category($category),
            HttpStatus::HTTP_OK,
        );
    }

    public function update(Request $request, string $slug)
    {
        $category = $this->categoryService->getBySlug($slug);

        $validated = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'status' => 'required|string|in:' . implode(',', array_column(ArticleCategoryStatus::options(), 'value')),
            'slug'      => [
                'required',
                'string',
                'max:255',
                'unique:article_categories,slug,' . $category->id,
            ],
            'parent_id' => ['nullable', 'integer', 'exists:article_categories,id'],
        ]);

        $updated = $this->categoryService->update($category, $validated);

        return sendResponse(
            true,
            'Category updated successfully',
            new Category($updated),
            HttpStatus::HTTP_OK,
        );
    }
    public function destroy(string $slug)
    {
        try {
            $this->categoryService->delete($slug);

            return sendResponse(
                true,
                'Category deleted successfully',
                null,
                HttpStatus::HTTP_OK,
            );
        } catch (\Exception $e) {
            return sendResponse(
                false,
                $e->getMessage(),
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    public function restore(string $slug)
    {
        $category = $this->categoryService->restore($slug);

        return sendResponse(
            true,
            'Category restored successfully',
            new Category($category),
            HttpStatus::HTTP_OK,
        );
    }

    public function forceDelete(string $slug)
    {
        $this->categoryService->forceDelete($slug);

        return sendResponse(
            true,
            'Category permanently deleted',
            null,
            HttpStatus::HTTP_OK,
        );
    }
}
