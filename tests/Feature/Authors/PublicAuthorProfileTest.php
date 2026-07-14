<?php

namespace Tests\Feature\Authors;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use App\Models\UserInformation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicAuthorProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);

        foreach (['users.profile', 'users.profile-update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Users'],
            );
        }

        Role::findByName('editor', 'api')->givePermissionTo([
            'users.profile',
            'users.profile-update',
        ]);
    }

    public function test_public_author_endpoint_returns_profile_and_published_articles_only(): void
    {
        $author = User::factory()->create([
            'name' => 'Sarah Johnson',
            'slug' => 'sarah-johnson',
        ]);

        UserInformation::query()->create([
            'user_id' => $author->id,
            'bio' => 'Award-winning journalist.',
            'public_title' => 'Editor-in-Chief',
            'social_links' => [
                'twitter' => 'https://twitter.com/example',
            ],
        ]);

        $category = ArticleCategory::query()->create([
            'title' => 'Politics',
            'slug' => 'politics',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        $published = Article::query()->create([
            'title' => 'Published Story',
            'slug' => 'published-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'article_category_id' => $category->id,
            'published_at' => '2026-03-15 10:00:00',
        ]);

        Article::query()->create([
            'title' => 'Draft Story',
            'slug' => 'draft-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::DRAFT,
            'user_id' => $author->id,
            'article_category_id' => $category->id,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/v1/authors/sarah-johnson');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.author.slug', 'sarah-johnson')
            ->assertJsonPath('data.author.name', 'Sarah Johnson')
            ->assertJsonPath('data.author.public_title', 'Editor-in-Chief')
            ->assertJsonPath('data.author.published_articles_count', 1)
            ->assertJsonMissingPath('data.author.email')
            ->assertJsonCount(1, 'data.articles')
            ->assertJsonPath('data.articles.0.slug', $published->slug)
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_unknown_author_slug_returns_not_found(): void
    {
        $this->getJson('/api/v1/authors/does-not-exist')->assertNotFound();
    }

    public function test_profile_update_persists_public_author_fields(): void
    {
        $user = User::factory()->create([
            'slug' => 'john-doe',
        ]);
        $user->assignRole('editor');

        UserInformation::query()->create([
            'user_id' => $user->id,
        ]);

        Passport::actingAs($user);

        $response = $this->putJson('/api/v1/admin/users/profile/update', [
            'name' => 'John Doe',
            'email' => $user->email,
            'slug' => 'john-updated',
            'bio' => 'Reporter covering local news.',
            'public_title' => 'Senior Reporter',
            'twitter' => 'https://twitter.com/john',
            'website' => 'https://example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.slug', 'john-updated')
            ->assertJsonPath('data.user_information.public_title', 'Senior Reporter')
            ->assertJsonPath('data.user_information.social_links.twitter', 'https://twitter.com/john');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'slug' => 'john-updated',
        ]);
    }
}
