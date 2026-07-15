<?php

namespace App\Services;

use App\Models\ArticleCategory;
use App\Models\SeoPage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CategoryService
{
    /**
     * Cache key for the public category payload, served by the frontend CategoryController.
     *
     * The cache deliberately wraps the *public controller's* read rather than
     * getAllCategories() itself: that method is also called by three admin endpoints, which
     * must always see live data — an admin editing categories cannot be served their own
     * stale list.
     */
    public const CACHE_PUBLIC = 'categories:public';

    /** 1 hour — categories are admin-edited and change rarely; every write path flushes below. */
    public const TTL_PUBLIC = 3600;

    public function __construct(
        private readonly SeoMetaService $seoMetaService,
    ) {}

    /**
     * Invalidate the cached public category payload.
     *
     * Every write path in this service calls this — including reorder() and moveToPosition(),
     * which change only sort_order. The cached payload is ordered by sort_order, so those two
     * change it just as much as a create or a delete does, despite not touching any row's
     * visible content. That is exactly why they are the easiest flushes to forget.
     */
    public function flushCache(): void
    {
        Cache::forget(self::CACHE_PUBLIC);
    }

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
        $this->flushCache();

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
        $this->flushCache();

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

        $this->flushCache();
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

        // Flushed by reorder() above, but flushed here too rather than depending on that
        // delegation: it is an implementation detail, and this method is one of the two
        // easiest places to lose invalidation if the call is ever inlined or replaced.
        $this->flushCache();
    }

    public function delete(string $slug): void
    {
        $category = $this->getBySlug($slug);

        if ($category->articles()->exists()) {
            throw new \Exception('Cannot delete category with existing articles.');
        }

        $category->delete();
        $this->flushCache();
    }

    public function restore(string $slug): ArticleCategory
    {
        $category = ArticleCategory::withTrashed()
            ->where('slug', $slug)
            ->firstOrFail();

        $category->restore();
        $this->flushCache();

        return $category;
    }

    public function forceDelete(string $slug): void
    {
        $category = ArticleCategory::withTrashed()
            ->where('slug', $slug)
            ->firstOrFail();

        $category->forceDelete();
        $this->flushCache();
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
