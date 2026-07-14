<?php

namespace App\Services;

use App\Models\ArticleCategory;
use App\Models\SeoPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CategoryService
{
    public function __construct(
        private readonly SeoMetaService $seoMetaService,
    ) {}

    public function getAllCategories(): Collection
    {
        return ArticleCategory::query()
            ->with([
                'parent:id,title,slug',
                'children' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Root categories with ordered children for public navigation.
     */
    public function getCategoryTree(): Collection
    {
        return ArticleCategory::query()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(array $data): ArticleCategory
    {
        $data = $this->seoMetaService->applyCategoryMeta($data);
        $data = $this->normalizeHierarchyAttributes($data);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = $this->nextSortOrder($data['parent_id'] ?? null);
        }

        $category = ArticleCategory::create($data);
        $this->syncSeoPage($category);

        return $category->load([
            'parent:id,title,slug',
            'children' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);
    }

    public function getBySlug(string $slug): ArticleCategory
    {
        return ArticleCategory::query()
            ->with([
                'parent:id,title,slug',
                'children' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function update(ArticleCategory $category, array $data): ArticleCategory
    {
        $data = $this->seoMetaService->applyCategoryMeta($data);
        $data = $this->normalizeHierarchyAttributes($data, $category);

        $oldParentId = $category->parent_id !== null ? (int) $category->parent_id : null;
        $newParentId = array_key_exists('parent_id', $data)
            ? ($data['parent_id'] !== null ? (int) $data['parent_id'] : null)
            : $oldParentId;

        if ($oldParentId !== $newParentId && ! isset($data['sort_order'])) {
            $data['sort_order'] = $this->nextSortOrder($newParentId);
        }

        $category->update($data);
        $category->refresh();
        $this->syncSeoPage($category);

        return $category->load([
            'parent:id,title,slug',
            'children' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);
    }

    /**
     * Reorder siblings under a parent (or roots when parent_id is null).
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(array $orderedIds, ?int $parentId = null): void
    {
        $orderedIds = array_values(array_map('intval', $orderedIds));
        $existingIds = $this->siblingQuery($parentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
        $incomingIds = collect($orderedIds)->sort()->values()->all();

        if ($existingIds !== $incomingIds) {
            throw new InvalidArgumentException(
                'Category reorder payload must include every sibling id for the given parent exactly once.',
            );
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                ArticleCategory::query()
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function moveToPosition(ArticleCategory $category, int $position): void
    {
        $parentId = $category->parent_id !== null ? (int) $category->parent_id : null;

        $ids = $this->siblingQuery($parentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $currentIndex = array_search((int) $category->id, $ids, true);

        if ($currentIndex === false) {
            throw new InvalidArgumentException('Category not found in reorder list.');
        }

        $count = count($ids);
        $position = max(1, min($position, $count));
        $targetIndex = $position - 1;

        if ($currentIndex === $targetIndex) {
            return;
        }

        array_splice($ids, $currentIndex, 1);
        array_splice($ids, $targetIndex, 0, [(int) $category->id]);

        $this->reorder($ids, $parentId);
    }

    public function delete(string $slug): void
    {
        $category = $this->getBySlug($slug);

        if ($category->articles()->exists()) {
            throw new \Exception('Cannot delete category with existing articles.');
        }

        if ($category->children()->exists()) {
            throw new \Exception('Cannot delete category with existing subcategories.');
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeHierarchyAttributes(array $data, ?ArticleCategory $existing = null): array
    {
        $parentId = array_key_exists('parent_id', $data)
            ? ($data['parent_id'] !== null ? (int) $data['parent_id'] : null)
            : ($existing?->parent_id !== null ? (int) $existing->parent_id : null);

        if ($parentId !== null) {
            if ($existing && $parentId === (int) $existing->id) {
                throw new InvalidArgumentException('A category cannot be its own parent.');
            }

            $parent = ArticleCategory::query()->find($parentId);

            if (! $parent) {
                throw new InvalidArgumentException('Parent category not found.');
            }

            if ($parent->parent_id !== null) {
                throw new InvalidArgumentException('Subcategories cannot have children. Choose a top-level parent.');
            }

            if ($existing && $existing->children()->exists()) {
                throw new InvalidArgumentException('Cannot nest a category that already has subcategories.');
            }

            $data['parent_id'] = $parentId;
            $data['is_featured'] = false;
        } elseif (array_key_exists('parent_id', $data)) {
            $data['parent_id'] = null;
        }

        return $data;
    }

    private function nextSortOrder(?int $parentId): int
    {
        return (int) $this->siblingQuery($parentId)->max('sort_order') + 1;
    }

    private function siblingQuery(?int $parentId): Builder
    {
        $query = ArticleCategory::query();

        if ($parentId === null) {
            return $query->whereNull('parent_id');
        }

        return $query->where('parent_id', $parentId);
    }

    private function syncSeoPage(ArticleCategory $category): void
    {
        SeoPage::updateOrCreate(
            ['page_key' => 'category-'.$category->slug],
            [
                'name' => $category->title.' Category',
                'url_path' => '/'.$category->slug,
                'is_template' => false,
                'meta_title' => $category->meta_title,
                'meta_description' => $category->meta_description,
                'meta_keywords' => $category->meta_keywords,
            ],
        );
    }
}
