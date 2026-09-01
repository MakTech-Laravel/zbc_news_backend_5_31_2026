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
            'site_name' => $this->site_name,
            'site_tag' => $this->site_tag,
            'site_logo' => MediaUrl::resolvePublic($this->site_logo),
            'favicon' => MediaUrl::resolvePublic($this->favicon),
            'header_layout' => $this->header_layout ?? 'stacked',
            'timezone' => $this->timezone,
            'language' => $this->language,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'default_category_id' => $this->default_category_id,
            'default_post_format' => $this->default_post_format,
            'enable_auto_save' => $this->enable_auto_save,
            'require_featured_image' => $this->require_featured_image,
            'posts_per_page' => $this->posts_per_page,
            'allow_comments' => (bool) ($this->allow_comments && $this->enable_comments),
            'authenticate_comment_only' => $this->authenticate_comment_only,
            'auto_approve_known_users' => $this->auto_approve_known_users,
            'related_article' => $this->related_article,
            'google_analytics_id' => $this->google_analytics_id ?? $this->g_messurment_id,
            'facebook_pixel_id' => $this->facebook_pixel_id ?? $this->pixeld_id,
            'google_adsense_client' => $this->google_adsense_client,
            'google_adsense_banner_slot' => $this->google_adsense_banner_slot,
            'google_adsense_sidebar_slot' => $this->google_adsense_sidebar_slot,
            'google_adsense_square_slot' => $this->google_adsense_square_slot,
            'disqus_shortname' => $this->disqus_shortname,
            'social_facebook_url' => $this->social_facebook_url,
            'social_x_url' => $this->social_x_url,
            'social_linkedin_url' => $this->social_linkedin_url,
            'social_tiktok_url' => $this->social_tiktok_url,
            'social_instagram_url' => $this->social_instagram_url,
            'contact_general_email' => $this->contact_general_email,
            'contact_press_email' => $this->contact_press_email,
            'contact_advertising_email' => $this->contact_advertising_email,
            'contact_corrections_email' => $this->contact_corrections_email,
            'contact_office_address' => $this->contact_office_address,
            'contact_office_maps_url' => $this->contact_office_maps_url,
            'frontend_url' => $this->publicAppUrl((string) config('app.frontend_url')),
            'api_url' => $this->publicAppUrl((string) config('app.url')),
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
