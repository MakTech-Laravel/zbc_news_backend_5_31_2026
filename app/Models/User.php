<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use App\Traits\HasMedia;
use Spatie\Permission\Traits\HasRoles;
#[Fillable([
    'name',
    'email',
    'password',
    'slug',
    'email_verified_at',
    'terms_accepted_at',
    'privacy_accepted_at',
    'deletion_requested_at',
    'scheduled_permanent_deletion_at',
    'deletion_cancel_token',
    'deletion_cancel_requested_at',
    'permanently_deleted_at',
])]
#[Hidden(['password', 'remember_token', 'deletion_cancel_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasMedia, HasRoles, Notifiable, LogsActivity, TwoFactorAuthenticatable;
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    protected $fillable = [

        'id',
        'name',
        'slug',
        'email',
        'email_verified_at',
        'terms_accepted_at',
        'privacy_accepted_at',
        'deletion_requested_at',
        'scheduled_permanent_deletion_at',
        'deletion_cancel_token',
        'deletion_cancel_requested_at',
        'permanently_deleted_at',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'scheduled_permanent_deletion_at' => 'datetime',
            'deletion_cancel_requested_at' => 'datetime',
            'permanently_deleted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null
            && $this->permanently_deleted_at === null;
    }

    public function hasDeletionCancelRequest(): bool
    {
        return $this->isPendingDeletion()
            && $this->deletion_cancel_requested_at !== null;
    }

    public function isPermanentlyDeleted(): bool
    {
        return $this->permanently_deleted_at !== null;
    }

    public function isDeletionBlocked(): bool
    {
        return $this->isPendingDeletion() || $this->isPermanentlyDeleted();
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! filled($user->slug)) {
                $user->slug = static::generateUniqueSlug((string) $user->name);
            }
        });
    }

    public static function generateUniqueSlug(string $base, ?int $excludeUserId = null): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'author';
        }

        $candidate = $slug;
        $count = 2;

        while (
            static::query()
                ->where('slug', $candidate)
                ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
                ->exists()
        ) {
            $candidate = "{$slug}-{$count}";
            $count++;
        }

        return $candidate;
    }

    public function guardName(): string
    {
        return 'api';
    }
    
    public function articles()
    {
        return $this->hasMany(Article::class, 'user_id');
    }

    public function saveArticles()
    {
        return $this->hasMany(SaveArticle::class, 'user_id');
    }
    
    public function notificationPreferences()
    {
        return $this->hasOne(NotificationPreference::class, 'user_id');
    }

    // public function readLogs()
    // {
    //     return $this->hasMany(ArticleReadLog::class, 'user_id');
    // }

    public function userInformation()
    {
        return $this->hasOne(UserInformation::class, 'user_id');
    }
    
    public function histroy()
    {
        return $this->hasMany(ArticleHistroy::class, 'user_id');
    }
}
