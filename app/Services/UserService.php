<?php

namespace App\Services;

use App\Models\Article;
use App\Models\User;
use App\Models\UserInformation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
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

        UserInformation::updateOrCreate(
            ['user_id' => $user->id],
            [
                'profile_image' => $profileImage ?? $user->userInformation?->profile_image,
                'bio'           => $data['bio']    ?? $user->userInformation?->bio,
                'region'        => $data['region'] ?? $user->userInformation?->region,
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
}
