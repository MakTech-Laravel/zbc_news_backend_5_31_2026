<?php

namespace Tests\Feature\Seo;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Database\Seeders\SeoPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoSitemapAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['app.frontend_url' => 'https://news.example']);

        foreach (['site-settings.list', 'site-settings.update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Site Settings'],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);
        $role->givePermissionTo(['site-settings.list', 'site-settings.update']);

        $author = User::factory()->create(['name' => 'Reporter', 'slug' => 'reporter']);
        $category = ArticleCategory::query()->create([
            'title' => 'Business',
            'slug' => 'business',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
        Article::query()->create([
            'title' => 'Recent Headline',
            'slug' => 'recent-headline',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'article_category_id' => $category->id,
            'published_at' => now()->subHours(2),
        ]);
        $this->seed(SeoPageSeeder::class);
        Cache::flush();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('editor');
        $this->admin->givePermissionTo(['site-settings.list', 'site-settings.update']);
        Passport::actingAs($this->admin);
    }

    public function test_general_download_is_byte_identical_to_public_endpoint(): void
    {
        $public = $this->get('/sitemap.xml')->assertOk()->getContent();
        $admin = $this->get('/api/v1/admin/seo/sitemap/download?type=general')->assertOk()->getContent();

        $this->assertSame($public, $admin);
    }

    public function test_news_download_is_byte_identical_to_public_endpoint(): void
    {
        $public = $this->get('/news-sitemap.xml')->assertOk()->getContent();
        $admin = $this->get('/api/v1/admin/seo/sitemap/download?type=news')->assertOk()->getContent();

        $this->assertSame($public, $admin);
    }

    public function test_robots_download_is_byte_identical_to_public_endpoint(): void
    {
        $public = $this->get('/robots.txt')->assertOk()->getContent();
        $admin = $this->get('/api/v1/admin/seo/robots/download')->assertOk()->getContent();

        $this->assertSame($public, $admin);
    }

    public function test_downloads_have_attachment_headers(): void
    {
        $this->get('/api/v1/admin/seo/sitemap/download?type=general')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="sitemap.xml"');

        $this->get('/api/v1/admin/seo/sitemap/download?type=news')
            ->assertHeader('Content-Disposition', 'attachment; filename="news-sitemap.xml"');

        $this->get('/api/v1/admin/seo/robots/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="robots.txt"');
    }

    public function test_refresh_busts_the_cache(): void
    {
        // Prime the cache with a stale value, then refresh and confirm it's gone.
        Cache::put('sitemap:general:xml', 'STALE', 3600);
        Cache::put('sitemap:news:xml', 'STALE', 600);

        $this->postJson('/api/v1/admin/seo/sitemap/refresh')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotSame('STALE', Cache::get('sitemap:general:xml'));
        $this->assertStringContainsString('<urlset', (string) Cache::get('sitemap:general:xml'));
        $this->assertNotSame('STALE', Cache::get('sitemap:news:xml'));
    }

    public function test_admin_endpoints_require_permission(): void
    {
        $noPerms = User::factory()->create();
        Passport::actingAs($noPerms);

        $this->postJson('/api/v1/admin/seo/sitemap/refresh')->assertForbidden();
        $this->getJson('/api/v1/admin/seo/sitemap/download?type=general')->assertForbidden();
        $this->getJson('/api/v1/admin/seo/robots/download')->assertForbidden();
    }
}
