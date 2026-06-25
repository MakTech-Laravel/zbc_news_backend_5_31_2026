<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'breaking_news'                => $this->breaking_news,
            'daily_newsletter'             => $this->daily_newsletter,
            'personalized_recommendations' => $this->personalized_recommendations,
            'comment_replies'              => $this->comment_replies,
            'saved_article_updates'        => $this->saved_article_updates,
            'platform_announcements'       => $this->platform_announcements,
        ];
    }
}
