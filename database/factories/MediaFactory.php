<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::ulid(),
            'cloudinary_public_id' => 'test/images/' . Str::random(10),
            'original_filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'resource_type' => 'image',
            'media_type' => 'image',
            'size' => 12345,
            'url' => 'https://res.cloudinary.com/test/image/upload/v1/test/file.jpg',
            'thumbnail_url' => 'https://res.cloudinary.com/test/image/upload/w_200/test/file.jpg',
            'collection' => 'default',
            'status' => 'ready',
            'mediable_type' => null,
            'mediable_id' => null,
            'uploaded_by' => User::factory(),
        ];
    }
}
