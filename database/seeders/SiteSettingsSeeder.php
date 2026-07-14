<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use App\Models\SiteSettings;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (SiteSettings::query()->exists()) {
            return;
        }

        $defaultCategoryId = ArticleCategory::query()->orderBy('id')->value('id');

        SiteSettings::create([
            'site_name' => 'ZBC News',
            'site_tag' => 'Breaking news and analysis from around the world',
            'site_logo' => 'https://res.cloudinary.com/dxkec4pmx/image/upload/v1783846484/logo_pdgu0p.png',
            'meta_title' => 'ZBC News — Breaking News, Analysis & Live Updates',
            'meta_description' => 'ZBC News brings you breaking news, in-depth analysis, and live updates across politics, business, technology, sports, and more.',
            'meta_keywords' => 'zbc news, breaking news, headlines, analysis, live updates',
            'timezone' => 'America/New_York',
            'language' => 'en',
            'default_category_id' => $defaultCategoryId,
            'default_post_format' => 'Standard',
            'enable_auto_save' => true,
            'require_featured_image' => false,
            'enable_ai_writing' => false,
            'posts_per_page' => 10,
            'allow_comments' => true,
            'authenticate_comment_only' => false,
            'auto_approve_known_users' => false,
            'related_article' => 3,
            'enable_comments' => true,
        ]);
    }
}
