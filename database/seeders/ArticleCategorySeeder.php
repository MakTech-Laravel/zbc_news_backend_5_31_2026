<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Enums\ArticleCategoryStatus;

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
                'created_at' => now(),
            ],
            [
                'id' => 2,
                'title' => 'Technology',
                'slug' => 'technology',
                'status' => ArticleCategoryStatus::ACTIVE->value,
                'created_at' => now(),
            ],
            [
                'id' => 3,
                'title' => 'Business',
                'slug' => 'business',
                'status' => ArticleCategoryStatus::ACTIVE->value,
                'created_at' => now(),
            ],
            [
                'id' => 4,
                'title' => 'Health',
                'slug' => 'health',
                'status' => ArticleCategoryStatus::ACTIVE->value,
                'created_at' => now(),
            ],
            [
                'id' => 5,
                'title' => 'Science',
                'slug' => 'science',
                'status' => ArticleCategoryStatus::ACTIVE->value,
                'created_at' => now(),
            ],
        ]);
    }
}
