<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn([
                'public_id' => 'test/images/file_abc123',
                'secure_url' => 'https://res.cloudinary.com/test/image/upload/v1/test/file.jpg',
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

            $mock->shouldReceive('thumbnail')->andReturn('https://res.cloudinary.com/test/image/upload/w_200,h_200/test/file.jpg');
        });
    }

    public function test_authenticated_user_can_upload_image(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->postJson('/api/v1/admin/media/store', ['file' => $file]);

        $response->assertStatus(201)
            ->assertJsonPath('data.media_type', 'image')
            ->assertJsonPath('data.status', 'ready');

        $this->assertDatabaseHas('media', [
            'uploaded_by' => $user->id,
            'status' => 'ready',
            'media_type' => 'image',
        ]);
    }

    public function test_unauthenticated_user_cannot_upload(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');
        $this->postJson('/api/v1/admin/media/store', ['file' => $file])->assertStatus(401);
    }

    public function test_user_can_delete_own_media(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $media = Media::factory()->create(['uploaded_by' => $user->id, 'status' => 'ready']);

        $this->mock(CloudinaryService::class, fn ($m) =>
            $m->shouldReceive('delete')->andReturn(true)
        );

        $this->deleteJson("/api/v1/admin/media/delete/{$media->uuid}")
            ->assertStatus(200);

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }
}
