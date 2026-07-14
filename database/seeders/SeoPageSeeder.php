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
            [
                'page_key' => 'tag',
                'name' => 'Tag (template)',
                'url_path' => '/tag/:tagSlug',
                'is_template' => true,
                'meta_title' => '{tag} — ZBC News',
                'meta_description' => 'Latest {tag} stories, analysis, and updates from ZBC News.',
                'meta_keywords' => '{tag}, news, articles',
            ],
            [
                'page_key' => 'newsletter',
                'name' => 'Newsletter',
                'url_path' => '/newsletter',
                'is_template' => false,
                'meta_title' => 'Newsletter',
                'meta_description' => 'Subscribe to the ZBC News daily newsletter for top headlines and category updates tailored to your interests.',
                'meta_keywords' => 'newsletter, subscribe, daily news, email',
            ],
            [
                'page_key' => 'about',
                'name' => 'About Us',
                'url_path' => '/about',
                'is_template' => false,
                'meta_title' => 'About Us — ZBC News',
                'meta_description' => 'Learn about ZBC News — our newsroom, mission, and the team behind the coverage.',
                'meta_keywords' => 'about, zbc news, newsroom, mission',
            ],
            [
                'page_key' => 'contact',
                'name' => 'Contact',
                'url_path' => '/contact',
                'is_template' => false,
                'meta_title' => 'Contact — ZBC News',
                'meta_description' => 'Get in touch with the ZBC News team — news tips, feedback, and enquiries.',
                'meta_keywords' => 'contact, news tips, feedback, enquiries',
            ],
            [
                'page_key' => 'privacy',
                'name' => 'Privacy Policy',
                'url_path' => '/privacy',
                'is_template' => false,
                'meta_title' => 'Privacy Policy — ZBC News',
                'meta_description' => 'How ZBC News collects, uses, and protects your personal information.',
                'meta_keywords' => 'privacy policy, data protection, privacy',
            ],
            [
                'page_key' => 'terms',
                'name' => 'Terms of Service',
                'url_path' => '/terms',
                'is_template' => false,
                'meta_title' => 'Terms of Service — ZBC News',
                'meta_description' => 'The terms and conditions governing your use of ZBC News.',
                'meta_keywords' => 'terms of service, terms and conditions',
            ],
            [
                'page_key' => 'cookie-policy',
                'name' => 'Cookie Policy',
                'url_path' => '/cookie-policy',
                'is_template' => false,
                'meta_title' => 'Cookie Policy — ZBC News',
                'meta_description' => 'How ZBC News uses cookies and similar technologies.',
                'meta_keywords' => 'cookie policy, cookies, tracking',
            ],
            [
                'page_key' => 'accessibility-statement',
                'name' => 'Accessibility Statement',
                'url_path' => '/accessibility-statement',
                'is_template' => false,
                'meta_title' => 'Accessibility Statement — ZBC News',
                'meta_description' => 'Our commitment to making ZBC News accessible to everyone.',
                'meta_keywords' => 'accessibility, a11y, accessible',
            ],
            [
                'page_key' => 'advertise',
                'name' => 'Advertise',
                'url_path' => '/advertise',
                'is_template' => false,
                'meta_title' => 'Advertise — ZBC News',
                'meta_description' => 'Advertising and partnership opportunities with ZBC News.',
                'meta_keywords' => 'advertise, advertising, partnerships, media kit',
            ],
            [
                'page_key' => 'careers',
                'name' => 'Careers',
                'url_path' => '/careers',
                'is_template' => false,
                'meta_title' => 'Careers — ZBC News',
                'meta_description' => 'Join the ZBC News team — current openings and life in our newsroom.',
                'meta_keywords' => 'careers, jobs, newsroom, hiring',
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
