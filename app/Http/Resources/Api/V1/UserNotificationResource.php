<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unread = $this->read_at === null;

        return [
            'id' => (string) $this->id,
            'tab' => $this->category->value,
            'title' => $this->title,
            'body' => $this->body,
            'time' => $this->created_at?->diffForHumans() ?? '',
            'icon' => $this->icon->value,
            'unread' => $unread,
            'showReadArticle' => $unread && filled($this->article_slug),
            'showMarkRead' => $unread,
            'articleSlug' => $this->article_slug,
        ];
    }
}
