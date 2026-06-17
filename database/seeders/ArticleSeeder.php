<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Article;

class ArticleSeeder extends Seeder
{
    private const PLACEHOLDER_IMAGE =
        'https://images.unsplash.com/photo-1504711331083-9c895941bf81?auto=format&fit=crop&w=1200&q=80';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Article::factory()->count(100)->create();

        Article::query()
            ->where('featured_image', '/storage/articles/featured-images/hxadz96PQN3Ahqjg9HWkbLiCvhv8LKRIGrK849MN.webp')
            ->orWhereNull('featured_image')
            ->update(['featured_image' => self::PLACEHOLDER_IMAGE]);
    }
}
