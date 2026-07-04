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
#[Fillable(['name', 'email', 'password', 'slug'])]
#[Hidden(['password', 'remember_token'])]
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
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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
