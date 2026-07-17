<?php

namespace Tests\Feature\Categories;

use App\Enums\ArticleCategoryStatus;
use App\Models\ArticleCategory;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The public categories endpoint is cached on a long TTL because categories are admin-edited
 * and change rarely. Every CategoryService write path must bust it.
 *
 * CategoryService has seven write paths and each gets its own test below. reorder() and
 * moveToPosition() are the two most likely to be missed — they mutate sort_order rather than
 * creating or deleting rows, so they read as "reordering" rather than "editing" — and the
 * cached tree is ordered by sort_order, so a missed flush there serves a stale order for a
 * full hour after an admin drags a category.
 */
class CategoryCacheTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(string $title, int $sortOrder, ?int $parentId = null): ArticleCategory
    {
        return ArticleCategory::query()->create([
            'title' => $title,
            'slug' => strtolower($title),
            'status' => ArticleCategoryStatus::ACTIVE->value,
            'sort_order' => $sortOrder,
            'parent_id' => $parentId,
        ]);
    }

    /** Titles as served by the cached public endpoint, in payload order. */
    private function publicTitles(): array
    {
        $response = $this->getJson('/api/v1/categories')->assertOk();

        return collect($response->json('data'))->pluck('title')->all();
    }

    /**
     * The warm request must not touch article_categories. It may still hit site_settings —
     * that lookup comes from the Category resource's SEO resolution, is cached separately by
     * SiteSettingsService, and is out of scope here; asserting zero total queries would be
     * asserting something this cache does not control.
     */
    public function test_repeat_request_is_served_from_cache(): void
    {
        $this->makeCategory('Politics', 1);

        $this->getJson('/api/v1/categories')->assertOk();

        $categoryQueries = [];
        DB::listen(function ($query) use (&$categoryQueries) {
            if (str_contains($query->sql, 'article_categories')) {
                $categoryQueries[] = $query->sql;
            }
        });

        $this->getJson('/api/v1/categories')->assertOk();

        $this->assertSame(
            [],
            $categoryQueries,
            'Warm cache must not re-query article_categories.',
        );
    }

    public function test_create_busts_the_cache(): void
    {
        $this->makeCategory('Politics', 1);
        $this->assertSame(['Politics'], $this->publicTitles());

        app(CategoryService::class)->create([
            'title' => 'Sport',
            'slug' => 'sport',
            'status' => ArticleCategoryStatus::ACTIVE->value,
        ]);

        $this->assertSame(['Politics', 'Sport'], $this->publicTitles());
    }

    public function test_update_busts_the_cache(): void
    {
        $category = $this->makeCategory('Politics', 1);
        $this->assertSame(['Politics'], $this->publicTitles());

        app(CategoryService::class)->update($category, ['title' => 'World']);

        $this->assertSame(['World'], $this->publicTitles());
    }

    /**
     * reorder() — flagged as easy to miss. It changes no titles, only sort_order, and the
     * cached payload is ordered by sort_order.
     */
    public function test_reorder_busts_the_cache(): void
    {
        $politics = $this->makeCategory('Politics', 1);
        $sport = $this->makeCategory('Sport', 2);

        $this->assertSame(['Politics', 'Sport'], $this->publicTitles(), 'Cache is now warm in the original order.');

        app(CategoryService::class)->reorder([$sport->id, $politics->id]);

        $this->assertSame(
            ['Sport', 'Politics'],
            $this->publicTitles(),
            'reorder() must bust the cache — otherwise the admin drags a category and the public order is stale for an hour.',
        );
    }

    /**
     * moveToPosition() — flagged as easy to miss. It currently delegates to reorder(), but it
     * also has an early return when the position is unchanged, and that delegation is an
     * implementation detail that could be refactored away. It is tested independently rather
     * than assumed to be covered by reorder()'s flush.
     */
    public function test_move_to_position_busts_the_cache(): void
    {
        $politics = $this->makeCategory('Politics', 1);
        $this->makeCategory('Sport', 2);
        $this->makeCategory('Business', 3);

        $this->assertSame(['Politics', 'Sport', 'Business'], $this->publicTitles(), 'Cache is now warm in the original order.');

        app(CategoryService::class)->moveToPosition($politics, 3);

        $this->assertSame(
            ['Sport', 'Business', 'Politics'],
            $this->publicTitles(),
            'moveToPosition() must bust the cache independently of reorder().',
        );
    }

    public function test_delete_busts_the_cache(): void
    {
        $this->makeCategory('Politics', 1);
        $this->makeCategory('Sport', 2);
        $this->assertSame(['Politics', 'Sport'], $this->publicTitles());

        app(CategoryService::class)->delete('sport');

        $this->assertSame(['Politics'], $this->publicTitles());
    }

    public function test_restore_busts_the_cache(): void
    {
        $this->makeCategory('Politics', 1);
        $this->makeCategory('Sport', 2);

        app(CategoryService::class)->delete('sport');
        $this->assertSame(['Politics'], $this->publicTitles(), 'Cache is now warm without the deleted category.');

        app(CategoryService::class)->restore('sport');

        $this->assertSame(['Politics', 'Sport'], $this->publicTitles());
    }

    public function test_force_delete_busts_the_cache(): void
    {
        $this->makeCategory('Politics', 1);
        $this->makeCategory('Sport', 2);

        app(CategoryService::class)->delete('sport');
        $this->assertSame(['Politics'], $this->publicTitles());

        app(CategoryService::class)->forceDelete('sport');

        $this->assertSame(['Politics'], $this->publicTitles(), 'Force-deleting an already soft-deleted category must not resurrect it from cache.');
    }

    /**
     * On this branch the public endpoint serves a flat list (getAllCategories), so a child is
     * just another row carrying a parent_id — it must still bust the cache when created.
     */
    public function test_creating_a_child_through_the_service_busts_the_cache(): void
    {
        $politics = $this->makeCategory('Politics', 1);
        $this->assertSame(['Politics'], $this->publicTitles(), 'Cache is now warm with no children.');

        app(CategoryService::class)->create([
            'title' => 'Elections',
            'slug' => 'elections',
            'status' => ArticleCategoryStatus::ACTIVE->value,
            'parent_id' => $politics->id,
        ]);

        $this->assertSame(['Politics', 'Elections'], $this->publicTitles(), 'A child created via the service must appear in the refreshed payload.');
    }

    /**
     * Scope boundary, asserted rather than assumed: invalidation hangs off CategoryService, so
     * a write made directly against the model does NOT bust the cache. Every admin route goes
     * through the service today (verified against the route table), so this is not a live bug —
     * but it pins the contract, and a future write path that bypasses the service must add its
     * own flush rather than inherit one.
     */
    public function test_direct_model_writes_bypass_invalidation_by_design(): void
    {
        $this->makeCategory('Politics', 1);
        $this->assertSame(['Politics'], $this->publicTitles(), 'Cache is now warm.');

        ArticleCategory::query()->create([
            'title' => 'Sport',
            'slug' => 'sport',
            'status' => ArticleCategoryStatus::ACTIVE->value,
            'sort_order' => 2,
        ]);

        $this->assertSame(
            ['Politics'],
            $this->publicTitles(),
            'A direct model write bypasses CategoryService and therefore its flush — new write paths must go through the service or flush explicitly.',
        );
    }
}
