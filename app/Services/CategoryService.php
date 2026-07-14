<?php

namespace App\Services;

use App\Models\ArticleCategory;
use App\Models\SeoPage;
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
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(array $data): ArticleCategory
    {
        $data = $this->seoMetaService->applyCategoryMeta($data);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (int) ArticleCategory::query()->max('sort_order') + 1;
        }

        $category = ArticleCategory::create($data);
        $this->syncSeoPage($category);

        return $category;
    }

    public function getBySlug(string $slug): ArticleCategory
    {
        return ArticleCategory::where('slug', $slug)->firstOrFail();
    }

    public function update(ArticleCategory $category, array $data): ArticleCategory
    {
        $data = $this->seoMetaService->applyCategoryMeta($data);
        $category->update($data);
        $category->refresh();
        $this->syncSeoPage($category);

        return $category;
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        $orderedIds = array_values(array_map('intval', $orderedIds));
        $existingIds = ArticleCategory::query()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $incomingIds = collect($orderedIds)->sort()->values()->all();

        if ($existingIds !== $incomingIds) {
            throw new InvalidArgumentException('Category reorder payload must include every category id exactly once.');
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
        $ids = ArticleCategory::query()
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

        $this->reorder($ids);
    }

    public function delete(string $slug): void
    {
        $category = $this->getBySlug($slug);

        if ($category->articles()->exists()) {
            throw new \Exception('Cannot delete category with existing articles.');
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
