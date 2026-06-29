<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        self::$sequence++;
        $index = self::$sequence;

        $title = 'Sample Article '.$index;

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$index,

            'meta_title' => Str::limit($title, 100, ''),
            'meta_description' => 'Sample meta description for article '.$index,

            'sub_title' => $index % 3 === 0 ? 'Sample subtitle' : null,

            'excerpt' => 'Sample excerpt for article '.$index,

            'article_description' => 'Sample article body content for article '.$index.'.',

            'status' => ArticleStatus::PUBLISHED->value,

            'visibility' => ArticleVisibility::PUBLIC->value,

            'featured_image' => 'https://images.unsplash.com/photo-1504711331083-9c895941bf81?auto=format&fit=crop&w=1200&q=80',
            'open_graph_image' => null,

            'scheduled_publishing' => null,

            'published_at' => now(),

            'views' => ($index * 47) % 5000,

            'article_category_id' => ArticleCategory::inRandomOrder()->first()->id,

            'user_id' => User::first()->id,

            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
