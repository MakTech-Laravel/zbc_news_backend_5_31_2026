<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user('api');
        $replies = $this->relationLoaded('nested_replies')
            ? $this->nested_replies
            : ($this->relationLoaded('replies') ? $this->replies : collect());

        return [
            'id' => (string) $this->id,
            'body' => $this->body,
            'authorName' => $this->authorName(),
            'authorAvatar' => $this->user?->avatar ?? null,
            'isOwn' => $user && $this->user_id && (int) $this->user_id === (int) $user->id,
            'status' => $this->status->value,
            'time' => $this->created_at?->diffForHumans() ?? '',
            'createdAtIso' => $this->created_at?->toIso8601String(),
            'parentId' => $this->parent_id ? (string) $this->parent_id : null,
            'articleId' => (string) $this->article_id,
            'articleTitle' => $this->whenLoaded('article', fn () => $this->article?->title),
            'articleSlug' => $this->whenLoaded('article', fn () => $this->article?->slug),
            'replies' => ArticleCommentResource::collection($replies),
        ];
    }
}
