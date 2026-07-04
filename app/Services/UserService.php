<?php

namespace App\Services;

use App\Models\Article;
use App\Models\User;
use App\Models\UserInformation;
use App\Enums\ArticleStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class UserService
{
    private const PROFILE_IMAGE_FOLDER = 'user_profiles';

    public function __construct(
        private readonly User $user,
        private readonly Activity $activity,
        private readonly Article $article,
        private readonly UserInformation $userInformation,
        private readonly NotificationPreferenceService $notificationPreferenceService,
        private readonly StoredImageService $storedImageService,
        private readonly SiteSettingsService $siteSettingsService,
    ) {}

    public function getAllUsers()
    {
        return $this->user->with('userInformation')->latest()->get();
    }

    public function getUserById($id)
    {
        return $this->user->with('userInformation')->find($id);
    }

    public function create(array $data)
    {
        $user = $this->user->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'slug' => $this->resolveSlug($data['slug'] ?? $data['name']),
        ]);

        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        }

        $this->notificationPreferenceService->getOrCreate($user);

        $profileImage = $data['profile_image'] ?? null;

        if ($profileImage instanceof UploadedFile) {
            $profileImage = $this->storedImageService->upload($profileImage, self::PROFILE_IMAGE_FOLDER);
        } else {
            $profileImage = $this->storedImageService->resolveValue($profileImage);
        }

        $this->userInformation->create([
            'user_id' => $user->id,
            'profile_image' => $profileImage,
            'bio'    => $data['bio'] ?? null,
            'region' => $data['region'] ?? null,
            'public_title' => $data['public_title'] ?? null,
            'social_links' => $this->normalizeSocialLinks($data),
        ]);

        return $user->load('userInformation');
    }

    public function updateUser($id, array $data): User
    {
        $user = $this->user->findOrFail($id);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update(Arr::only($data, ['name', 'email', 'password']));

        if (array_key_exists('slug', $data)) {
            $user->update([
                'slug' => $this->resolveSlug($data['slug'] ?: $data['name'] ?? $user->name, $user->id),
            ]);
        } elseif (! $user->slug) {
            $user->update([
                'slug' => $this->resolveSlug($user->name, $user->id),
            ]);
        }

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        $profileImage = null;

        if (array_key_exists('profile_image', $data)) {
            $currentImage = $user->userInformation?->profile_image;

            if ($data['profile_image'] instanceof UploadedFile) {
                $this->storedImageService->delete($currentImage);
                $profileImage = $this->storedImageService->upload($data['profile_image'], self::PROFILE_IMAGE_FOLDER);
            } else {
                $resolved = $this->storedImageService->resolveValue($data['profile_image']);

                if ($this->storedImageService->isDifferent($currentImage, $resolved)) {
                    $this->storedImageService->delete($currentImage);
                    $profileImage = $resolved;
                }
            }
        }

        $socialLinks = $this->normalizeSocialLinks($data, $user->userInformation?->social_links ?? []);

        UserInformation::updateOrCreate(
            ['user_id' => $user->id],
            [
                'profile_image' => $profileImage ?? $user->userInformation?->profile_image,
                'bio'           => array_key_exists('bio', $data) ? $data['bio'] : $user->userInformation?->bio,
                'region'        => array_key_exists('region', $data) ? $data['region'] : $user->userInformation?->region,
                'public_title'  => array_key_exists('public_title', $data) ? $data['public_title'] : $user->userInformation?->public_title,
                'social_links'  => $socialLinks,
            ]
        );

        return $user->fresh(['userInformation']);
    }

    public function deleteUser($id): bool
    {
        $user = $this->user->findOrFail($id);

        if (auth()->id() === $user->id) {
            throw new \Exception('You cannot delete your own account.', 403);
        }

        if ($user->hasRole('super_admin')) {
            $superAdminCount = $this->user->role('super_admin')->count();

            if ($superAdminCount <= 1) {
                throw new \Exception('At least one super admin must remain in the system.', 403);
            }
        }

        if ($user->userInformation?->profile_image) {
            $this->storedImageService->delete($user->userInformation->profile_image);
        }

        $user->syncRoles([]);

        return $user->delete();
    }

    public function getUserArticleActivities(int $userId)
    {
        return $this->activity->query()
            ->where('causer_type', User::class)
            ->where('causer_id', $userId)
            ->where('subject_type', $this->article::class)
            ->with(['subject'])
            ->latest()
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'article_id' => $activity->subject?->id,
                    'article_title' => $activity->subject?->title,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'old' => $activity->properties['old'] ?? null,
                    'new' => $activity->properties['new'] ?? null,
                    'created_at' => $activity->created_at,
                ];
            });
    }

    /**
     * @return array{user: User, published_count: int, items: \Illuminate\Support\Collection, meta: array<string, int>}
     */
    public function getPublicAuthorBySlug(string $slug, ?int $perPage = null, int $page = 1): array
    {
        $user = $this->user
            ->where('slug', $slug)
            ->with('userInformation')
            ->firstOrFail();

        $publishedQuery = $this->article
            ->where('user_id', $user->id)
            ->where('status', ArticleStatus::PUBLISHED->value)
            ->whereNotNull('published_at');

        $publishedCount = (clone $publishedQuery)->count();

        $perPage = $perPage ?? $this->siteSettingsService->getPostsPerPage();
        $paginator = (clone $publishedQuery)
            ->with(['tags', 'category', 'user'])
            ->latest('published_at')
            ->paginate($perPage, ['*'], 'page', max(1, $page));

        return [
            'user' => $user,
            'published_count' => $publishedCount,
            'items' => $paginator->getCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function resolveSlug(string $base, ?int $excludeUserId = null): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'author';
        }

        $candidate = $slug;
        $count = 2;

        while (
            $this->user
                ->where('slug', $candidate)
                ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
                ->exists()
        ) {
            $candidate = "{$slug}-{$count}";
            $count++;
        }

        return $candidate;
    }

    public function backfillMissingUserSlugs(): int
    {
        $updated = 0;

        $this->user->query()
            ->where(function ($query): void {
                $query->whereNull('slug')
                    ->orWhere('slug', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$updated): void {
                foreach ($users as $user) {
                    if (filled($user->slug)) {
                        continue;
                    }

                    $user->forceFill([
                        'slug' => $this->resolveSlug($user->name, $user->id),
                    ])->saveQuietly();

                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existing
     * @return array<string, string>|null
     */
    private function normalizeSocialLinks(array $data, array $existing = []): ?array
    {
        $keys = ['facebook', 'twitter', 'linkedin', 'instagram', 'youtube', 'website'];
        $links = $existing;

        if (isset($data['social_links']) && is_array($data['social_links'])) {
            foreach ($data['social_links'] as $key => $value) {
                if (! is_string($key) || ! in_array($key, $keys, true)) {
                    continue;
                }

                $trimmed = is_string($value) ? trim($value) : '';
                if ($trimmed === '') {
                    unset($links[$key]);
                } else {
                    $links[$key] = $trimmed;
                }
            }
        }

        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $trimmed = is_string($data[$key]) ? trim($data[$key]) : '';
            if ($trimmed === '') {
                unset($links[$key]);
            } else {
                $links[$key] = $trimmed;
            }
        }

        return $links === [] ? null : $links;
    }
}
