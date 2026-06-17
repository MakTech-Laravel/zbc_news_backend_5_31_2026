<?php

namespace Database\Factories;

use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1000, 9999),

            'meta_title' => fake()->text(100),
            'meta_description' => fake()->text(200),

            'sub_title' => fake()->optional()->text(100),

            'excerpt' => fake()->text(250),

            'article_description' => fake()->paragraphs(5, true),

            'status' => fake()->randomElement([
                'draft',
                'published',
                'scheduled'
            ]),

            'visibility' => fake()->randomElement([
                'public',
                'premium'
            ]),

            'featured_image' => 'https://images.unsplash.com/photo-1504711331083-9c895941bf81?auto=format&fit=crop&w=1200&q=80',
            'open_graph_image' => null,

            'scheduled_publishing' => null,

            'published_at' => now(),

            'views' => fake()->numberBetween(0, 5000),

            'article_category_id' => ArticleCategory::inRandomOrder()->first()->id,

            'user_id' => User::first()->id,

            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}