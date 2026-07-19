<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_name' => 'nullable|string|max:255',
            'site_tag' => 'nullable|string|max:255',
            'site_logo' => 'nullable|string|max:2048',
            'favicon' => 'nullable|string|max:2048',
            'timezone' => 'nullable|string|max:64',
            'language' => 'nullable|string|max:16',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:2000',
            'meta_keywords' => 'nullable|string|max:500',
            'default_category_id' => 'nullable|exists:article_categories,id',
            'default_post_format' => 'nullable|string|max:32',
            'enable_auto_save' => 'boolean',
            'require_featured_image' => 'boolean',
            'enable_ai_writing' => 'boolean',
            'posts_per_page' => 'integer|min:1|max:100',
            'allow_comments' => 'boolean',
            'authenticate_comment_only' => 'boolean',
            'auto_approve_known_users' => 'boolean',
            'related_article' => 'integer|min:0|max:50',
            'pixeld_id' => 'nullable|string|max:255',
            'g_messurment_id' => 'nullable|string|max:255',
            'g_api_secrete' => 'nullable|string|max:255',
            'google_analytics_id' => 'nullable|string|max:255',
            'facebook_pixel_id' => 'nullable|string|max:255',
            'google_adsense_client' => 'nullable|string|max:100',
            'google_adsense_banner_slot' => 'nullable|string|max:100',
            'google_adsense_sidebar_slot' => 'nullable|string|max:100',
            'google_adsense_square_slot' => 'nullable|string|max:100',
            'mailchimp_api_key' => 'nullable|string|max:255',
            'newsletter_provider' => 'nullable|string|in:smtp,resend,brevo,mailchimp',
            'newsletter_from_email' => 'nullable|email|max:255',
            'newsletter_from_name' => 'nullable|string|max:255',
            'resend_api_key' => 'nullable|string|max:255',
            'brevo_api_key' => 'nullable|string|max:255',
            'mailchimp_list_id' => 'nullable|string|max:255',
            'disqus_shortname' => 'nullable|string|max:255',
            'slack_webhook_url' => 'nullable|string|max:500',
            'enable_comments' => 'boolean',
        ];
    }
}
