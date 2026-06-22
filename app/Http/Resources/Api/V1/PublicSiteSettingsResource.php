<?php

namespace App\Http\Resources\Api\V1;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSiteSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'site_name'                 => $this->site_name,
            'site_tag'                  => $this->site_tag,
            'site_logo'                 => MediaUrl::resolvePublic($this->site_logo),
            'timezone'                  => $this->timezone,
            'language'                  => $this->language,
            'meta_title'                => $this->meta_title,
            'meta_description'          => $this->meta_description,
            'meta_keywords'             => $this->meta_keywords,
            'default_category_id'       => $this->default_category_id,
            'default_post_format'       => $this->default_post_format,
            'enable_auto_save'          => $this->enable_auto_save,
            'require_featured_image'    => $this->require_featured_image,
            'posts_per_page'            => $this->posts_per_page,
            'allow_comments'            => (bool) ($this->allow_comments && $this->enable_comments),
            'authenticate_comment_only' => $this->authenticate_comment_only,
            'auto_approve_known_users'  => $this->auto_approve_known_users,
            'related_article'           => $this->related_article,
            'google_analytics_id'       => $this->google_analytics_id ?? $this->g_messurment_id,
            'facebook_pixel_id'         => $this->facebook_pixel_id ?? $this->pixeld_id,
            'disqus_shortname'          => $this->disqus_shortname,
            'frontend_url'              => $this->publicAppUrl((string) config('app.frontend_url')),
            'api_url'                   => $this->publicAppUrl((string) config('app.url')),
        ];
    }

    private function publicAppUrl(string $url): ?string
    {
        $normalized = rtrim(trim($url), '/');

        if ($normalized === '') {
            return null;
        }

        $host = parse_url($normalized, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) || str_ends_with($host, '.local')) {
            return null;
        }

        return $normalized;
    }
}
