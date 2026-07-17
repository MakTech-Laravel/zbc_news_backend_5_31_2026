<?php

namespace Tests\Feature\Cache;

use App\Enums\ArticleCategoryStatus;
use App\Models\AdSlot;
use App\Models\ArticleCategory;
use App\Models\NavigationLink;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for cached payload serialization.
 *
 * phpunit.xml runs the whole suite on CACHE_STORE=array, which keeps live PHP objects in
 * memory and never serializes. Every other cache store (database, redis, file) serializes its
 * payload — and Eloquent models do not survive that round trip in this environment: they come
 * back as __PHP_Incomplete_Class and every cached endpoint 500s on its second request.
 *
 * The array driver cannot reproduce that, so these tests force a serializing store and assert
 * the public endpoints still answer correctly when served from a warm cache. They are the only
 * tests in the suite that exercise the serialization boundary; without them, caching an
 * Eloquent collection looks perfectly green while being broken in every real environment.
 */
class SerializedCachePayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The file store serializes exactly like database/redis, without needing either.
        config()->set('cache.default', 'file');
        app('cache')->store('file')->clear();
    }

    protected function tearDown(): void
    {
        app('cache')->store('file')->clear();

        parent::tearDown();
    }

    /**
     * Each endpoint is hit twice: the second request is served from the warm, serialized cache
     * and is the one that used to fail.
     *
     * Comparing cold to warm is necessary but NOT sufficient, and an earlier version of this
     * helper stopped there and passed while categories was serving corrupt data: a payload
     * carrying an unserializable object degrades the *same way* on both requests, so the two
     * match perfectly and both are wrong. The __PHP_Incomplete_Class assertion is what actually
     * catches it — that marker is what a failed unserialize leaves behind in a live response.
     */
    private function assertEndpointSurvivesSerializedCache(string $uri): void
    {
        $cold = $this->getJson($uri)->assertOk();
        $warm = $this->getJson($uri)->assertOk();

        $this->assertStringNotContainsString(
            '__PHP_Incomplete_Class',
            $warm->getContent(),
            "{$uri} served an unserializable object from the cache — the response is a 200 carrying corrupt data.",
        );

        $this->assertSame(
            $cold->json(),
            $warm->json(),
            "{$uri} returned a different payload from the serialized cache than from the database.",
        );
    }

    /**
     * A child category is essential here, not incidental. The Category resource builds
     * `children` with whenLoaded(...map(...)), and map() on an Eloquent collection returns an
     * Eloquent collection — the object that breaks the cache. With no child rows, children is
     * empty, that branch never runs, and this test passes against genuinely broken code.
     */
    public function test_categories_survives_a_serializing_cache_store(): void
    {
        $politics = ArticleCategory::query()->create([
            'title' => 'Politics',
            'slug' => 'politics',
            'status' => ArticleCategoryStatus::ACTIVE->value,
            'sort_order' => 1,
        ]);

        ArticleCategory::query()->create([
            'title' => 'Elections',
            'slug' => 'elections',
            'status' => ArticleCategoryStatus::ACTIVE->value,
            'sort_order' => 1,
            'parent_id' => $politics->id,
        ]);

        $this->assertEndpointSurvivesSerializedCache('/api/v1/categories');
    }

    /**
     * The nested children payload must survive the cache as real data, not as the marker a
     * failed unserialize leaves behind. This is the exact corruption seen in production: a 200
     * response where every category's children was
     * {"__PHP_Incomplete_Class_Name":"Illuminate\\Database\\Eloquent\\Collection"}.
     */
    public function test_categories_children_survive_the_cache_as_real_data(): void
    {
        $politics = ArticleCategory::query()->create([
            'title' => 'Politics',
            'slug' => 'politics',
            'status' => ArticleCategoryStatus::ACTIVE->value,
            'sort_order' => 1,
        ]);

        ArticleCategory::query()->create([
            'title' => 'Elections',
            'slug' => 'elections',
            'status' => ArticleCategoryStatus::ACTIVE->value,
            'sort_order' => 1,
            'parent_id' => $politics->id,
        ]);

        $this->getJson('/api/v1/categories')->assertOk();

        $warm = $this->getJson('/api/v1/categories')->assertOk();

        $parent = collect($warm->json('data'))->firstWhere('slug', 'politics');

        $this->assertIsArray($parent['children'], 'children must decode as a plain array, not an object marker.');
        $this->assertSame('Elections', $parent['children'][0]['title'] ?? null);
        $this->assertSame('active', $parent['children'][0]['status'] ?? null, 'The child status enum must serialize to its string value.');
    }

    public function test_trending_tags_survives_a_serializing_cache_store(): void
    {
        Tag::query()->create(['tag' => 'politics']);

        $this->assertEndpointSurvivesSerializedCache('/api/v1/trending-tags');
    }

    public function test_quick_links_survives_a_serializing_cache_store(): void
    {
        NavigationLink::query()->create([
            'location' => NavigationLink::LOCATION_QUICK_LINKS,
            'label' => 'Politics',
            'url' => '/politics',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertEndpointSurvivesSerializedCache('/api/v1/navigation/quick-links');
    }

    public function test_ad_slots_survives_a_serializing_cache_store(): void
    {
        AdSlot::query()->create([
            'slot_key' => 'home_top',
            'name' => 'Home Top',
            'provider' => 'manual',
            'is_active' => true,
            'manual_image_url' => 'https://example.test/a.png',
        ]);

        $this->assertEndpointSurvivesSerializedCache('/api/v1/ads/slots');
    }

    /**
     * ads/slots is keyed by slot_key, so its payload is a JSON object rather than a list.
     * toArray() runs after keyBy() to preserve that; this pins the shape through the cache.
     */
    public function test_ad_slots_keeps_its_keyed_shape_through_the_cache(): void
    {
        AdSlot::query()->create([
            'slot_key' => 'home_top',
            'name' => 'Home Top',
            'provider' => 'manual',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/ads/slots')->assertOk();

        $this->getJson('/api/v1/ads/slots')
            ->assertOk()
            ->assertJsonPath('data.home_top.slot_key', 'home_top');
    }

    /**
     * trending-tags is rehydrated from cached rows, so the aggregate the ranking depends on
     * must survive the round trip rather than silently returning null.
     */
    public function test_trending_tags_keeps_its_articles_count_through_the_cache(): void
    {
        Tag::query()->create(['tag' => 'politics']);

        $service = app(TagService::class);

        $cold = $service->getTrendingTags();
        $warm = $service->getTrendingTags();

        $this->assertInstanceOf(Collection::class, $warm);
        $this->assertInstanceOf(Tag::class, $warm->first());
        $this->assertSame(
            $cold->first()->articles_count,
            $warm->first()->articles_count,
            'articles_count must survive the cache round trip — it is what the ranking sorts on.',
        );
    }
}
