<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'mediable_id',
        'mediable_type',
        'cloudinary_public_id',
        'cloudinary_version',
        'cloudinary_signature',
        'original_filename',
        'disk_name',
        'mime_type',
        'extension',
        'resource_type',
        'media_type',
        'size',
        'metadata',
        'url',
        'thumbnail_url',
        'preview_url',
        'folder',
        'collection',
        'status',
        'error_message',
        'upload_attempts',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
        'upload_attempts' => 'integer',
    ];

    protected $hidden = [
        'cloudinary_signature',
    ];

    protected static function booted(): void
    {
        static::creating(function (Media $model) {
            $model->uuid ??= (string) Str::ulid();
        });
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function transformations(): HasMany
    {
        return $this->hasMany(MediaTransformation::class);
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('media_type', $type);
    }

    public function scopeInCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    public function isImage(): bool
    {
        return $this->media_type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function isDocument(): bool
    {
        return $this->media_type === 'document';
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    public function getTransformationUrl(string $key): ?string
    {
        return $this->transformations->firstWhere('key', $key)?->url;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
