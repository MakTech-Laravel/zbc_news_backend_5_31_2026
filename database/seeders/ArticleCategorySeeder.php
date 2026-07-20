<?php

namespace Database\Seeders;

use App\Enums\ArticleCategoryStatus;
use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;

class ArticleCategorySeeder extends Seeder
{
    /**
     * Parent categories with sample subcategories for nav / admin testing.
     *
     * @return array<int, array{title: string, slug: string, sort_order: int, is_featured?: bool, children: list<array{title: string, slug: string}>}>
     */
    private function tree(): array
    {
        return [
            [
                'title' => 'General',
                'slug' => 'general',
                'sort_order' => 1,
                'is_featured' => true,
                'children' => [
                    ['title' => 'Local News', 'slug' => 'local-news'],
                    ['title' => 'National', 'slug' => 'national'],
                    ['title' => 'Opinion', 'slug' => 'opinion'],
                ],
            ],
            [
                'title' => 'Breaking News',
                'slug' => 'breaking-news',
                'sort_order' => 2,
                'is_featured' => true,
                'children' => [
                    ['title' => 'Latest Updates', 'slug' => 'latest-updates'],
                    ['title' => 'Live Coverage', 'slug' => 'live-coverage'],
                    ['title' => 'Top Stories', 'slug' => 'top-stories'],
                ],
            ],
            [
                'title' => 'World',
                'slug' => 'world',
                'sort_order' => 3,
                'is_featured' => true,
                'children' => [
                    ['title' => 'Asia', 'slug' => 'asia'],
                    ['title' => 'Europe', 'slug' => 'europe'],
                    ['title' => 'Americas', 'slug' => 'americas'],
                ],
            ],
            [
                'title' => 'Politics',
                'slug' => 'politics',
                'sort_order' => 4,
                'is_featured' => true,
                'children' => [
                    ['title' => 'Government', 'slug' => 'government'],
                    ['title' => 'Elections', 'slug' => 'elections'],
                    ['title' => 'Policy', 'slug' => 'policy'],
                ],
            ],
            [
                'title' => 'Technology',
                'slug' => 'technology',
                'sort_order' => 5,
                'is_featured' => true,
                'children' => [
                    ['title' => 'Gadgets', 'slug' => 'gadgets'],
                    ['title' => 'Apps & Software', 'slug' => 'apps-software'],
                    ['title' => 'AI & Innovation', 'slug' => 'ai-innovation'],
                ],
            ],
            [
                'title' => 'Business',
                'slug' => 'business',
                'sort_order' => 6,
                'is_featured' => true,
                'children' => [
                    ['title' => 'Markets', 'slug' => 'markets'],
                    ['title' => 'Startups', 'slug' => 'startups'],
                    ['title' => 'Economy', 'slug' => 'economy'],
                ],
            ],
            [
                'title' => 'Health',
                'slug' => 'health',
                'sort_order' => 7,
                'is_featured' => false,
                'children' => [
                    ['title' => 'Wellness', 'slug' => 'wellness'],
                    ['title' => 'Medical Research', 'slug' => 'medical-research'],
                    ['title' => 'Public Health', 'slug' => 'public-health'],
                ],
            ],
            [
                'title' => 'Science',
                'slug' => 'science',
                'sort_order' => 8,
                'is_featured' => false,
                'children' => [
                    ['title' => 'Space', 'slug' => 'space'],
                    ['title' => 'Environment', 'slug' => 'environment'],
                    ['title' => 'Climate', 'slug' => 'climate'],
                ],
            ],
            [
                'title' => 'Sports',
                'slug' => 'sports',
                'sort_order' => 9,
                'is_featured' => true,
                'children' => [
                    ['title' => 'Football', 'slug' => 'football'],
                    ['title' => 'Cricket', 'slug' => 'cricket'],
                    ['title' => 'Tennis', 'slug' => 'tennis'],
                ],
            ],
            [
                'title' => 'Entertainment',
                'slug' => 'entertainment',
                'sort_order' => 10,
                'is_featured' => true,
                'children' => [
                    ['title' => 'Movies', 'slug' => 'movies'],
                    ['title' => 'TV Shows', 'slug' => 'tv-shows'],
                    ['title' => 'Celebrity', 'slug' => 'celebrity'],
                ],
            ],
            [
                'title' => 'Video',
                'slug' => 'video',
                'sort_order' => 11,
                'is_featured' => false,
                'children' => [
                    ['title' => 'Interviews', 'slug' => 'interviews'],
                    ['title' => 'Documentaries', 'slug' => 'documentaries'],
                    ['title' => 'Live Streams', 'slug' => 'live-streams'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach ($this->tree() as $parentData) {
            $parent = ArticleCategory::query()->updateOrCreate(
                ['slug' => $parentData['slug']],
                [
                    'title' => $parentData['title'],
                    'status' => ArticleCategoryStatus::ACTIVE,
                    'parent_id' => null,
                    'sort_order' => $parentData['sort_order'],
                    'is_featured' => (bool) ($parentData['is_featured'] ?? false),
                ],
            );

            foreach ($parentData['children'] as $index => $childData) {
                ArticleCategory::query()->updateOrCreate(
                    ['slug' => $childData['slug']],
                    [
                        'title' => $childData['title'],
                        'status' => ArticleCategoryStatus::ACTIVE,
                        'parent_id' => $parent->id,
                        'sort_order' => $index + 1,
                        'is_featured' => false,
                    ],
                );
            }
        }

        $this->command?->info('Article categories and subcategories seeded successfully');
    }
}
