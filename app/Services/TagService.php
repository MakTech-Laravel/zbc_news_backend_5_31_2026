<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Support\Facades\Cache;

class TagService
{
    private const CACHE_TRENDING = 'tags:trending';

    private const TTL_TRENDING = 600;      // 10 minutes — counts shift with article writes, not tag writes.

    /**
     * Create a new class instance.
     */
    public function __construct(private Tag $tag) {}

    public function getAllTags()
    {
        return $this->tag->all();
    }

    public function getTagById($id)
    {
        return $this->tag->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->tag->create($data);
    }

    public function update($id, array $data)
    {
        $tag = $this->getTagById($id);
        $tag->update($data);

        return $tag;
    }

    public function delete($id)
    {
        $tag = $this->getTagById($id);
        $tag->delete();
    }

    public function restore(string $id): Tag
    {
        $tag = Tag::withTrashed()
            ->where('id', $id)
            ->firstOrFail();

        $tag->restore();

        return $tag;
    }

    public function forceDelete(string $id): void
    {
        $tag = Tag::withTrashed()
            ->where('id', $id)
            ->firstOrFail();

        $tag->forceDelete();
    }

    /**
     * Trending tags, cached on a short TTL rather than invalidated on write.
     *
     * The ranking is driven by article writes (publishing shifts the counts), not by tag
     * writes, so hooking the tag write paths would look correct while missing every real
     * cause of change. Flushing on every article write instead would invalidate a
     * site-wide aggregate constantly, for data that is decorative ranking rather than
     * correctness-critical. A short TTL bounds the staleness at a cost users can't see.
     */
    public function getTrendingTags(int $limit = 10)
    {
        return Cache::remember(
            self::CACHE_TRENDING.':'.$limit,
            self::TTL_TRENDING,
            fn () => $this->tag
                ->withCount('articles')
                ->orderBy('articles_count', 'desc')
                ->limit($limit)
                ->get(),
        );
    }
}
