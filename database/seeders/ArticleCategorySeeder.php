<?php

namespace Database\Seeders;

use App\Enums\ArticleCategoryStatus;
use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;

class ArticleCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ArticleCategory::insert([
            [
                'id' => 1,
                'title' => 'General',
                'slug' => 'general',
                'status' => ArticleCategoryStatus::ACTIVE->value,
                'sort_order' => 1,
                'created_at' => now(),
            ],
            [
                'id' => 2,
                'title' => 'Technology',
                'slug' => 'technology',
                'status' => ArticleCategoryStatus::ACTIVE->value,
                'sort_order' => 2,
                'created_at' => now(),
            ],
            [
                'id' => 3,
                'title' => 'Business',
                'slug' => 'business',
                'status' => ArticleCategoryStatus::ACTIVE->value,
                'sort_order' => 3,
                'created_at' => now(),
            ],
            [
                'id' => 4,
                'title' => 'Health',
                'slug' => 'health',
                'status' => ArticleCategoryStatus::ACTIVE->value,
                'sort_order' => 4,
                'created_at' => now(),
            ],
            [
                'id' => 5,
                'title' => 'Science',
                'slug' => 'science',
                'status' => ArticleCategoryStatus::ACTIVE->value,
                'sort_order' => 5,
                'created_at' => now(),
            ],
        ]);
    }
}
