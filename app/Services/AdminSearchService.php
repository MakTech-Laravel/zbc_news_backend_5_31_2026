<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleComment;
use App\Models\Media;
use App\Models\User;

class AdminSearchService
{
    public function search(User $user, string $query, int $limit = 5): array
    {
        $term = trim($query);
        if ($term === '' || mb_strlen($term) < 2) {
            return [
                'articles' => [],
                'users' => [],
                'categories' => [],
                'media' => [],
                'comments' => [],
            ];
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return [
            'articles' => $user->can(PermissionEnum::ARTICLES_LIST->value)
                ? $this->searchArticles($like, $limit)
                : [],
            'users' => $user->can(PermissionEnum::USERS_LIST->value)
                ? $this->searchUsers($like, $limit)
                : [],
            'categories' => $user->can(PermissionEnum::CATEGORIES_LIST->value)
                ? $this->searchCategories($like, $limit)
                : [],
            'media' => $user->can(PermissionEnum::MEDIA_LIST->value)
                ? $this->searchMedia($like, $limit)
                : [],
            'comments' => $user->can(PermissionEnum::COMMENTS_LIST->value)
                ? $this->searchComments($like, $limit)
                : [],
        ];
    }

    private function searchArticles(string $like, int $limit): array
    {
        return Article::query()
            ->with('user')
            ->where(function ($query) use ($like) {
                $query->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('excerpt', 'like', $like);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Article $article) => [
                'id' => (string) $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'status' => $article->status?->value ?? (string) $article->status,
                'author' => $article->user?->name,
                'updated_at' => $article->updated_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    private function searchUsers(string $like, int $limit): array
    {
        return User::query()
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'updated_at' => $user->updated_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    private function searchCategories(string $like, int $limit): array
    {
        return ArticleCategory::query()
            ->where(function ($query) use ($like) {
                $query->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ArticleCategory $category) => [
                'id' => (string) $category->id,
                'title' => $category->title,
                'slug' => $category->slug,
                'status' => $category->status?->value ?? (string) $category->status,
            ])
            ->values()
            ->all();
    }

    private function searchMedia(string $like, int $limit): array
    {
        return Media::query()
            ->where(function ($query) use ($like) {
                $query->where('original_filename', 'like', $like)
                    ->orWhere('mime_type', 'like', $like)
                    ->orWhere('collection', 'like', $like);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Media $media) => [
                'id' => (string) $media->id,
                'uuid' => $media->uuid,
                'name' => $media->original_filename ?? $media->uuid,
                'mime_type' => $media->mime_type,
                'updated_at' => $media->updated_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    private function searchComments(string $like, int $limit): array
    {
        return ArticleComment::query()
            ->with(['user', 'article'])
            ->where(function ($query) use ($like) {
                $query->where('body', 'like', $like)
                    ->orWhere('guest_name', 'like', $like)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $like));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (ArticleComment $comment) => [
                'id' => (string) $comment->id,
                'body' => mb_substr($comment->body, 0, 120),
                'author' => $comment->authorName(),
                'status' => $comment->status->value,
                'article_title' => $comment->article?->title,
                'article_slug' => $comment->article?->slug,
            ])
            ->values()
            ->all();
    }
}
