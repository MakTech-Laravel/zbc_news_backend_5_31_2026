<?php

namespace App\Services;

use App\Models\Tag;

class TagService
{
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
}
