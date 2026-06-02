<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly Article $article
    ) {}


    public function getAllArticles()
    {
        return $this->article->all();
    }

    public function getArticleById($id)
    {
        return $this->article->find($id);
    }

    public function create(array $data): Article
    {
        return DB::transaction(function () use ($data) {
            $data['slug']           = $this->resolveSlug($data);
            $data['status']         = $this->resolveStatus($data);
            $data['published_at']   = $this->resolvePublishedAt($data);
            $data['featured_image'] = $this->resolveFeaturedImage($data);
            $data['user_id']        = auth()->user()->id;

            $article = $this->article->create($data);

            // Activity Log
            activity()
                ->performedOn($article)
                ->causedBy(auth()->user())
                ->withProperties([
                    'article_title'         => $article->title,
                    'article_slug'          => $article->slug,
                    'status'                => $article->status,
                    'article_category_id'   => $article->article_category_id,
                    'scheduled_publishing'  => $article->scheduled_publishing,
                    'published_at'          => $article->published_at,
                    'ip_address'            => request()->ip(),
                    'user_agent'            => request()->userAgent(),
                ])
                ->log('Article created');

            return $article;
        });
    }

    private function resolveSlug(array $data): string
    {
        $base  = Str::slug(!empty($data['slug']) ? $data['slug'] : $data['title']);
        $slug  = $base;
        $count = 1;

        while ($this->article->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }
    private function resolveStatus(array $data): string
    {
        $status = $data['status'] ?? ArticleStatus::DRAFT->value;
        if ($status === ArticleStatus::SCHEDULED->value && empty($data['scheduled_publishing'])) {
            throw new \InvalidArgumentException('Scheduled publishing date is required for scheduled articles.');
        }

        return $status;
    }
    private function resolvePublishedAt(array $data): ?\Carbon\Carbon
    {
        $status = $data['status'] ?? ArticleStatus::DRAFT->value;

        return match ($status) {
            ArticleStatus::PUBLISHED->value  => isset($data['published_at'])
                ? \Carbon\Carbon::parse($data['published_at'])
                : now(),
            ArticleStatus::SCHEDULED->value  => null,
            default => null,
        };
    }

    private function resolveFeaturedImage(array $data): ?string
    {
        if (empty($data['featured_image']) || !$data['featured_image'] instanceof UploadedFile) {
            return null;
        }

        $path = $data['featured_image']->store('articles/featured-images', 'public');

        return Storage::url($path);
    }


    public function update(string $slug, array $data): Article
    {
        $article = $this->article
            ->where('slug', $slug)
            ->firstOrFail();

        return DB::transaction(function () use ($article, $data) {

            $data['slug']           = $this->resolveSlug($data, $article->id);
            $data['status']         = $this->resolveStatus($data);
            $data['published_at']   = $this->resolvePublishedAt($data, $article);
            $data['featured_image'] = $this->resolveFeaturedImage($data, $article);

            $old = $article->only([
                'title',
                'slug',
                'status',
                'article_category_id',
                'scheduled_publishing',
                'published_at'
            ]);

            $article->update($data);

            activity()
                ->performedOn($article)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old'        => $old,
                    'new'        => $article->fresh()->only(array_keys($old)),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->log('Article updated');

            return $article->fresh();
        });
    }
}
