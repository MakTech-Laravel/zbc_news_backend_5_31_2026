<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use App\Models\SeoPage;
use Illuminate\Database\Seeder;

class SeoPageSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'page_key' => 'home',
                'name' => 'Home',
                'url_path' => '/',
                'is_template' => false,
                'meta_title' => 'ZBC News — Home',
                'meta_description' => 'Latest headlines, breaking news, and featured stories from ZBC News.',
                'meta_keywords' => 'news, home, headlines, breaking news',
            ],
            [
                'page_key' => 'category',
                'name' => 'Category (template)',
                'url_path' => '/:categorySlug',
                'is_template' => true,
                'meta_title' => '{category} News',
                'meta_description' => 'Browse the latest {category} stories, analysis, and updates on ZBC News.',
                'meta_keywords' => '{category}, news, articles',
            ],
            [
                'page_key' => 'article-detail',
                'name' => 'Article detail (template)',
                'url_path' => '/news-details/:articleSlug',
                'is_template' => true,
                'meta_title' => '{title} — ZBC News',
                'meta_description' => '{excerpt}',
                'meta_keywords' => '{tags}, news',
            ],
            [
                'page_key' => 'news-details',
                'name' => 'News details landing',
                'url_path' => '/news-details',
                'is_template' => false,
                'meta_title' => 'News — ZBC News',
                'meta_description' => 'Read full news articles and in-depth coverage on ZBC News.',
                'meta_keywords' => 'news, articles, stories',
            ],
            [
                'page_key' => 'author-profile',
                'name' => 'Author profile (template)',
                'url_path' => '/author/:authorSlug',
                'is_template' => true,
                'meta_title' => '{author} — ZBC News',
                'meta_description' => '{bio}',
                'meta_keywords' => '{author}, author, news, articles',
            ],
        ];

        foreach ($templates as $row) {
            SeoPage::updateOrCreate(
                ['page_key' => $row['page_key']],
                $row,
            );
        }

        ArticleCategory::query()
            ->where('status', 'active')
            ->orderBy('title')
            ->each(function (ArticleCategory $category) {
                SeoPage::updateOrCreate(
                    ['page_key' => 'category-'.$category->slug],
                    [
                        'name' => $category->title.' Category',
                        'url_path' => '/'.$category->slug,
                        'is_template' => false,
                        'meta_title' => $category->title.' News',
                        'meta_description' => 'Latest '.strtolower($category->title).' news, analysis, and updates from ZBC News.',
                        'meta_keywords' => strtolower($category->title).', news, articles',
                    ],
                );
            });
    }
}
