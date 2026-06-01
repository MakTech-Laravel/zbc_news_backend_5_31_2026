<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ArticleCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ArticleCategory::insert(
            [
                'title' => 'General',
                'slug' => 'general',
            ],
            [
                'title' => 'Technology',
                'slug' => 'technology',
            ],
            [
                'title' => 'Business',
                'slug' => 'business',
            ],
            [
                'title' => 'Health',
                'slug' => 'health',
            ],
            [
                'title' => 'Science',
                'slug' => 'science',
            ]
        );
    }
}
