<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'site_name'                 => $this->site_name,
            'site_tag'                  => $this->site_tag,
            'site_logo'                 => $this->site_logo
                                            ? asset('storage/' . $this->site_logo)
                                            : null,
            'timezone'                  => $this->timezone,
            'default_category_id'       => $this->default_category_id,
            'posts_per_page'            => $this->posts_per_page,
            'allow_comments'            => $this->allow_comments,
            'authenticate_comment_only' => $this->authenticate_comment_only,
            'related_article'           => $this->related_article,
            'pixeld_id'                 => $this->pixeld_id,
            'g_messurment_id'           => $this->g_messurment_id,
            'g_api_secrete'             => $this->g_api_secrete,
            'enable_comments'           => $this->enable_comments,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
        ];
    }
}
