<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TagsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            'TopHeadlines',
            'Politics',
            'Economy',
            'Sports',
            'Entertainment',
            'Technology',
            'Health',
            'Science',
            'World',
            'Business',
            'Opinion',
        ];

        foreach ($tags as $tag) {
            Tag::create([
                'name' => $tag,
            ]);
        }
    }
}
