<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class NotificationPreference extends Model
{
    public const DEFAULTS = [
        'breaking_news' => true,
        'daily_newsletter' => true,
        'personalized_recommendations' => true,
        'comment_replies' => false,
        'saved_article_updates' => false,
    ];

    protected $fillable = [
        'user_id',
        'breaking_news',
        'daily_newsletter',
        'personalized_recommendations',
        'comment_replies',
        'saved_article_updates',
    ];

    protected function casts(): array
    {
        return [
            'breaking_news' => 'boolean',
            'daily_newsletter' => 'boolean',
            'personalized_recommendations' => 'boolean',
            'comment_replies' => 'boolean',
            'saved_article_updates' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function wants(int $userId, string $field): bool
    {
        if (! array_key_exists($field, self::DEFAULTS)) {
            return false;
        }

        $preference = static::query()->where('user_id', $userId)->first();

        if (! $preference) {
            return (bool) self::DEFAULTS[$field];
        }

        return (bool) $preference->{$field};
    }

    /**
     * @param  Collection<int, int|string>|array<int, int|string>  $userIds
     * @return Collection<int, int>
     */
    public static function filterUserIds(Collection|array $userIds, string $field): Collection
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $preferences = static::query()
            ->whereIn('user_id', $ids)
            ->get()
            ->keyBy('user_id');

        return $ids->filter(function (int $userId) use ($preferences, $field) {
            $preference = $preferences->get($userId);

            if (! $preference) {
                return (bool) self::DEFAULTS[$field];
            }

            return (bool) $preference->{$field};
        })->values();
    }

    public static function ensureForUser(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            self::DEFAULTS,
        );
    }
}
