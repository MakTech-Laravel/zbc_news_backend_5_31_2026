<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Services\SeoMetaService;
use Illuminate\Database\Seeder;

class EntitySeoMetaSeeder extends Seeder
{
    public function run(): void
    {
        $seoMetaService = app(SeoMetaService::class);

        ArticleCategory::query()->each(function (ArticleCategory $category) use ($seoMetaService) {
            $category->update($seoMetaService->applyCategoryMeta([
                'title'              => $category->title,
                'slug'               => $category->slug,
                'meta_title'         => $category->meta_title,
                'meta_description'   => $category->meta_description,
                'meta_keywords'      => $category->meta_keywords,
            ]));
        });

        Article::query()->with(['tags', 'category'])->chunkById(50, function ($articles) use ($seoMetaService) {
            foreach ($articles as $article) {
                $article->update($seoMetaService->applyArticleMeta([
                    'title'                => $article->title,
                    'excerpt'              => $article->excerpt,
                    'article_description'  => $article->article_description,
                    'meta_title'           => $article->meta_title,
                    'meta_description'     => $article->meta_description,
                    'meta_keywords'        => $article->meta_keywords,
                ], $article->tags->pluck('tag')->all(), $article->category?->title));
            }
        });
    }
}
