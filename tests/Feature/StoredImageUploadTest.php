<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use App\Models\UserInformation;
use App\Services\CloudinaryService;
use App\Services\StoredImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoredImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private const CLOUDINARY_URL = 'https://res.cloudinary.com/test/image/upload/v1/test/file.jpg';

    private const CLOUDINARY_URL_REPLACEMENT = 'https://res.cloudinary.com/test/image/upload/v1/test/file-new.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seedPermissions();
        $this->mockCloudinary();
    }

    public function test_profile_update_uploads_image_to_cloudinary(): void
    {
        $user = $this->createUserWithProfilePermissions();
        Passport::actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->call(
            'PUT',
            '/api/v1/admin/users/profile/update',
            [
                'name' => $user->name,
                'email' => $user->email,
            ],
            [],
            ['profile_image' => $file],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $response->assertOk()
            ->assertJsonPath('data.user_information.profile_image', self::CLOUDINARY_URL);

        $this->assertDatabaseHas('user_information', [
            'user_id' => $user->id,
            'profile_image' => self::CLOUDINARY_URL,
        ]);
    }

    public function test_profile_update_without_changing_image_does_not_delete_cloudinary_asset(): void
    {
        $user = $this->createUserWithProfilePermissions();
        UserInformation::query()->create([
            'user_id' => $user->id,
            'profile_image' => self::CLOUDINARY_URL,
        ]);

        Passport::actingAs($user);

        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('delete')->never();
            $mock->shouldReceive('upload')->never();
            $mock->shouldReceive('publicIdFromUrl')->never();
        });

        $response = $this->putJson('/api/v1/admin/users/profile/update', [
            'name' => 'Updated Name',
            'email' => $user->email,
            'profile_image' => self::CLOUDINARY_URL,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.user_information.profile_image', self::CLOUDINARY_URL);
    }

    public function test_article_store_uploads_featured_image_to_cloudinary(): void
    {
        $user = $this->createUserWithArticlePermissions();
        $category = $this->createCategory();
        Passport::actingAs($user);

        $file = UploadedFile::fake()->image('featured.jpg', 1200, 800);

        $response = $this->post('/api/v1/admin/articles/store', [
            'title' => 'Cloudinary Article',
            'slug' => 'cloudinary-article',
            'article_description' => 'Article body content.',
            'article_category_id' => $category->id,
            'status' => 'draft',
            'visibility' => 'public',
            'featured_image' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.featured_image', self::CLOUDINARY_URL);

        $this->assertDatabaseHas('articles', [
            'slug' => 'cloudinary-article',
            'featured_image' => self::CLOUDINARY_URL,
        ]);
    }

    public function test_article_update_replaces_featured_image_and_deletes_previous(): void
    {
        $user = $this->createUserWithArticlePermissions();
        $category = $this->createCategory();

        $article = Article::query()->create([
            'title' => 'Replace Image Article',
            'slug' => 'replace-image-article',
            'article_description' => 'Body',
            'status' => 'draft',
            'visibility' => 'public',
            'article_category_id' => $category->id,
            'user_id' => $user->id,
            'featured_image' => self::CLOUDINARY_URL,
        ]);

        Passport::actingAs($user);

        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('publicIdFromUrl')
                ->once()
                ->with(self::CLOUDINARY_URL)
                ->andReturn('test/file');

            $mock->shouldReceive('delete')
                ->once()
                ->with('test/file', 'image')
                ->andReturn(true);

            $mock->shouldReceive('upload')
                ->once()
                ->andReturn([
                    'secure_url' => self::CLOUDINARY_URL_REPLACEMENT,
                    'public_id' => 'test/file-new',
                    'resource_type' => 'image',
                    'version' => '2',
                    'signature' => 'sig',
                    '_media_type' => 'image',
                    '_mime' => 'image/jpeg',
                ]);
        });

        $file = UploadedFile::fake()->image('replacement.jpg', 1200, 800);

        $response = $this->post('/api/v1/admin/articles/update/'.$article->slug, [
            'title' => $article->title,
            'article_description' => $article->article_description,
            'article_category_id' => $category->id,
            'status' => 'draft',
            'visibility' => 'public',
            'featured_image' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.featured_image', self::CLOUDINARY_URL_REPLACEMENT);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'featured_image' => self::CLOUDINARY_URL_REPLACEMENT,
        ]);
    }

    public function test_stored_image_service_deletes_local_file(): void
    {
        Storage::disk('public')->put('user_profiles/local.jpg', 'image-data');

        $service = app(StoredImageService::class);
        $service->delete('user_profiles/local.jpg');

        Storage::disk('public')->assertMissing('user_profiles/local.jpg');
    }

    public function test_migrate_images_command_dry_run_lists_local_profile_images(): void
    {
        Storage::disk('public')->put('user_profiles/local.jpg', 'image-data');

        $user = User::factory()->create();
        $profile = UserInformation::query()->create([
            'user_id' => $user->id,
            'profile_image' => 'user_profiles/local.jpg',
        ]);

        $this->artisan('images:migrate-to-cloudinary', ['--dry-run' => true])
            ->expectsOutputToContain('Would migrate user_information#'.$profile->id)
            ->assertSuccessful();
    }

    private function mockCloudinary(): void
    {
        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn([
                'public_id' => 'test/images/file_abc123',
                'secure_url' => self::CLOUDINARY_URL,
                'bytes' => 12345,
                'resource_type' => 'image',
                'version' => '12345',
                'signature' => 'sig_abc',
                '_media_type' => 'image',
                '_original_name' => 'test.jpg',
                '_size' => 12345,
                '_mime' => 'image/jpeg',
                '_extension' => 'jpg',
                'width' => 800,
                'height' => 600,
            ]);

            $mock->shouldReceive('publicIdFromUrl')->andReturn('test/file');
            $mock->shouldReceive('delete')->andReturn(true);
        });
    }

    private function seedPermissions(): void
    {
        $definitions = [
            'users.profile' => 'Users',
            'users.profile-update' => 'Users',
            'articles.create' => 'Articles',
            'articles.update' => 'Articles',
        ];

        foreach ($definitions as $name => $group) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => $group],
            );
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'editor', 'guard_name' => 'api'],
        );

        $role->givePermissionTo(array_keys($definitions));
    }

    private function createUserWithProfilePermissions(): User
    {
        $user = User::factory()->create();
        $user->assignRole('editor');
        $user->givePermissionTo(['users.profile', 'users.profile-update']);

        return $user;
    }

    private function createUserWithArticlePermissions(): User
    {
        $user = User::factory()->create();
        $user->assignRole('editor');
        $user->givePermissionTo(['articles.create', 'articles.update']);

        return $user;
    }

    private function createCategory(): ArticleCategory
    {
        return ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general',
            'status' => 'active',
        ]);
    }
}
