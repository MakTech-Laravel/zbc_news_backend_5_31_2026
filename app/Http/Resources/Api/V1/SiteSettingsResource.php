<?php

namespace App\Http\Resources\Api\V1;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_name' => $this->site_name,
            'site_tag' => $this->site_tag,
            'site_logo' => MediaUrl::resolvePublic($this->site_logo),
            'favicon' => MediaUrl::resolvePublic($this->favicon),
            'timezone' => $this->timezone,
            'language' => $this->language,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'default_category_id' => $this->default_category_id,
            'default_post_format' => $this->default_post_format,
            'enable_auto_save' => $this->enable_auto_save,
            'require_featured_image' => $this->require_featured_image,
            'enable_ai_writing' => $this->enable_ai_writing,
            'posts_per_page' => $this->posts_per_page,
            'allow_comments' => $this->allow_comments,
            'authenticate_comment_only' => $this->authenticate_comment_only,
            'auto_approve_known_users' => $this->auto_approve_known_users,
            'related_article' => $this->related_article,
            'pixeld_id' => $this->pixeld_id,
            'g_messurment_id' => $this->g_messurment_id,
            'g_api_secrete' => $this->g_api_secrete,
            'google_analytics_id' => $this->google_analytics_id ?? $this->g_messurment_id,
            'facebook_pixel_id' => $this->facebook_pixel_id ?? $this->pixeld_id,
            'google_adsense_client' => $this->google_adsense_client,
            'google_adsense_banner_slot' => $this->google_adsense_banner_slot,
            'google_adsense_sidebar_slot' => $this->google_adsense_sidebar_slot,
            'google_adsense_square_slot' => $this->google_adsense_square_slot,
            'mailchimp_api_key' => $this->mailchimp_api_key,
            'newsletter_provider' => $this->newsletter_provider ?? 'smtp',
            'newsletter_from_email' => $this->newsletter_from_email,
            'newsletter_from_name' => $this->newsletter_from_name,
            'resend_api_key' => $this->resend_api_key,
            'brevo_api_key' => $this->brevo_api_key,
            'mailchimp_list_id' => $this->mailchimp_list_id,
            'disqus_shortname' => $this->disqus_shortname,
            'slack_webhook_url' => $this->slack_webhook_url,
            'enable_comments' => $this->enable_comments,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
