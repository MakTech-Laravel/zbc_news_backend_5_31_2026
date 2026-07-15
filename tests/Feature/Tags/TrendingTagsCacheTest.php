<?php

namespace Tests\Feature\Tags;

use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trending tags are cached on a short TTL with no write-path invalidation — the ranking is
 * driven by article writes, not tag writes. These tests pin that intent: the cache must hit,
 * and it must expire rather than depend on a flush that nothing calls.
 */
class TrendingTagsCacheTest extends TestCase
{
    use RefreshDatabase;

    private function makeTags(): void
    {
        foreach (['politics', 'sport', 'business'] as $tag) {
            Tag::query()->create(['tag' => $tag]);
        }
    }

    public function test_first_call_queries_and_second_call_is_served_from_cache(): void
    {
        $this->makeTags();
        $service = app(TagService::class);

        $firstCallQueries = 0;
        DB::listen(function () use (&$firstCallQueries) {
            $firstCallQueries++;
        });

        $first = $service->getTrendingTags();

        $this->assertGreaterThan(0, $firstCallQueries, 'Cold cache should hit the database.');

        $secondCallQueries = 0;
        DB::listen(function () use (&$secondCallQueries) {
            $secondCallQueries++;
        });

        $second = $service->getTrendingTags();

        $this->assertSame(0, $secondCallQueries, 'Warm cache must not re-query the database.');
        $this->assertEquals($first->pluck('tag'), $second->pluck('tag'));
    }

    public function test_public_endpoint_serves_trending_tags_from_cache_on_repeat_request(): void
    {
        $this->makeTags();

        $this->getJson('/api/v1/trending-tags')->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->getJson('/api/v1/trending-tags')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, $queries, 'Repeat public request must be served from cache.');
    }

    public function test_new_tag_is_not_visible_until_cache_expires(): void
    {
        $this->makeTags();
        $service = app(TagService::class);

        $this->assertCount(3, $service->getTrendingTags());

        Tag::query()->create(['tag' => 'weather']);

        $this->assertCount(
            3,
            $service->getTrendingTags(),
            'Trending tags are TTL-only by design: a tag write must NOT bust the cache.',
        );

        Cache::flush();

        $this->assertCount(
            4,
            $service->getTrendingTags(),
            'Once the cache lapses the new tag appears — staleness is bounded by TTL, not by a flush hook.',
        );
    }

    public function test_different_limits_do_not_collide_in_the_cache(): void
    {
        $this->makeTags();
        $service = app(TagService::class);

        $this->assertCount(2, $service->getTrendingTags(2));
        $this->assertCount(3, $service->getTrendingTags(3), 'A different limit must not return the cached payload of another limit.');
    }
}
