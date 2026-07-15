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
     * and is the one that used to fail. The payloads must be identical — a cache that changes
     * the response shape is as broken as one that throws.
     */
    private function assertEndpointSurvivesSerializedCache(string $uri): void
    {
        $cold = $this->getJson($uri)->assertOk();
        $warm = $this->getJson($uri)->assertOk();

        $this->assertSame(
            $cold->json(),
            $warm->json(),
            "{$uri} returned a different payload from the serialized cache than from the database.",
        );
    }

    public function test_categories_survives_a_serializing_cache_store(): void
    {
        ArticleCategory::query()->create([
            'title' => 'Politics',
            'slug' => 'politics',
            'status' => ArticleCategoryStatus::ACTIVE->value,
            'sort_order' => 1,
        ]);

        $this->assertEndpointSurvivesSerializedCache('/api/v1/categories');
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
