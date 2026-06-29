<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArticleSeeder extends Seeder
{
    private const PLACEHOLDER_IMAGE =
        'https://images.unsplash.com/photo-1504711331083-9c895941bf81?auto=format&fit=crop&w=1200&q=80';

    private const SAMPLE_BODY =
        'This is sample article content for development and testing purposes. '
        .'It provides placeholder text for the article body until real editorial content is added.';

    private const SAMPLE_TITLES = [
        'National Budget Debate Opens in Parliament',
        'Local Tech Startup Secures Major Investment',
        'Healthcare Workers Call for Improved Facilities',
        'Sports Team Clinches Regional Championship Title',
        'New Education Policy Aims to Boost Literacy Rates',
        'Business Leaders Discuss Economic Growth Strategies',
        'Scientists Announce Breakthrough in Renewable Energy',
        'Government Launches Infrastructure Development Plan',
        'Community Festival Celebrates Cultural Heritage',
        'Weather Service Warns of Heavy Rains This Week',
        'Tourism Sector Reports Record Visitor Numbers',
        'Farmers Adopt Modern Techniques to Increase Yield',
        'City Council Approves New Public Transport Routes',
        'University Researchers Publish Landmark Study',
        'Entertainment Industry Hosts Annual Awards Ceremony',
        'Trade Agreement Expected to Boost Exports',
        'Youth Employment Program Creates New Opportunities',
        'Environmental Group Launches Reforestation Campaign',
        'Bank Announces Lower Interest Rates for Small Businesses',
        'National Team Prepares for Upcoming International Tournament',
    ];

    public function run(): void
    {
        $userId = User::query()->value('id');
        $categoryIds = ArticleCategory::query()->pluck('id')->all();

        if ($userId === null || $categoryIds === []) {
            return;
        }

        $statuses = [
            ArticleStatus::DRAFT->value,
            ArticleStatus::PUBLISHED->value,
            ArticleStatus::SCHEDULED->value,
        ];

        $visibilities = [
            ArticleVisibility::PUBLIC->value,
            ArticleVisibility::PREMIUM->value,
        ];

        $now = now();

        for ($i = 0; $i < 100; $i++) {
            $title = self::SAMPLE_TITLES[$i % count(self::SAMPLE_TITLES)].' '.($i + 1);
            $status = $statuses[$i % count($statuses)];
            $isPublished = $status === ArticleStatus::PUBLISHED->value;

            Article::create([
                'title' => $title,
                'slug' => Str::slug($title).'-'.($i + 1000),
                'meta_title' => Str::limit($title, 100, ''),
                'meta_description' => Str::limit(self::SAMPLE_BODY, 200, ''),
                'sub_title' => $i % 3 === 0 ? 'A closer look at the latest developments' : null,
                'excerpt' => Str::limit(self::SAMPLE_BODY, 250, ''),
                'article_description' => self::SAMPLE_BODY,
                'status' => $status,
                'visibility' => $visibilities[$i % count($visibilities)],
                'featured_image' => self::PLACEHOLDER_IMAGE,
                'open_graph_image' => null,
                'scheduled_publishing' => $status === ArticleStatus::SCHEDULED->value ? $now->copy()->addDays($i + 1) : null,
                'published_at' => $isPublished ? $now->copy()->subDays($i % 30) : null,
                'views' => ($i * 47) % 5000,
                'article_category_id' => $categoryIds[$i % count($categoryIds)],
                'user_id' => $userId,
            ]);
        }
    }
}
