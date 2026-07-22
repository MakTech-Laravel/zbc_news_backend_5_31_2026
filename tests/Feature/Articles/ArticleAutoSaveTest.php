<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\SiteSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArticleAutoSaveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ArticleCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->user->assignRole('editor');
        $this->user->givePermissionTo(['articles.create', 'articles.update']);

        Passport::actingAs($this->user);

        $this->category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        SiteSettings::query()->create([
            'site_name' => 'ZBC News',
            'enable_auto_save' => true,
            'default_category_id' => $this->category->id,
        ]);
    }

    public function test_auto_save_creates_draft_article(): void
    {
        $response = $this->postJson('/api/v1/admin/articles/auto-save', [
            'title' => 'Auto saved title',
            'article_description' => '<p>Draft body</p>',
            'slug' => 'auto-saved-title',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.slug', 'auto-saved-title');

        $this->assertDatabaseHas('articles', [
            'title' => 'Auto saved title',
            'slug' => 'auto-saved-title',
            'status' => ArticleStatus::DRAFT->value,
        ]);
    }

    public function test_auto_save_update_preserves_published_status(): void
    {
        $article = Article::query()->create([
            'title' => 'Published article',
            'slug' => 'published-article',
            'article_description' => '<p>Original body</p>',
            'status' => ArticleStatus::PUBLISHED->value,
            'article_category_id' => $this->category->id,
            'published_at' => now()->subDay(),
            'user_id' => $this->user->id,
        ]);

        $frozenUpdatedAt = now()->subDays(2)->startOfSecond();
        $article->timestamps = false;
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();
        $article->timestamps = true;
        $article->refresh();

        $response = $this->postJson('/api/v1/admin/articles/auto-save/'.$article->slug, [
            'title' => 'Updated published title',
            'article_description' => '<p>Updated body</p>',
            'status' => ArticleStatus::DRAFT->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.success', true);

        $article->refresh();

        $this->assertSame(ArticleStatus::PUBLISHED->value, $article->status->value);
        $this->assertSame('Updated published title', $article->title);
        $this->assertSame(
            $frozenUpdatedAt->utc()->format('Y-m-d H:i:s'),
            $article->updated_at->utc()->format('Y-m-d H:i:s'),
            'Auto-save must not change updated_at',
        );
    }

    private function seedPermissions(): void
    {
        foreach (['articles.create', 'articles.update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Articles'],
            );
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'editor', 'guard_name' => 'api'],
        );

        $role->givePermissionTo(['articles.create', 'articles.update']);
    }
}
