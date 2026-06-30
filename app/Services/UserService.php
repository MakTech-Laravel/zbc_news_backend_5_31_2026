<?php

namespace App\Services;

use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use App\Models\Article;
use App\Models\UserInformation;
use App\Services\NotificationPreferenceService;
use Illuminate\Support\Arr;

class UserService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly User $user,
        private readonly Activity $activity,
        private readonly Article $article,
        private readonly UserInformation $userInformation,
        private readonly NotificationPreferenceService $notificationPreferenceService,
        private readonly CloudinaryService $cloudinary,
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

        $this->userInformation->create([
            'user_id' => $user->id,
            'profile_image' => $this->resolveProfileImageValue($data['profile_image'] ?? null),
            'bio'    => $data['bio'] ?? null,
            'region' => $data['region'] ?? null,
        ]);

        return $user->load('userInformation');
    }

    public function updateUser($id, array $data): User
    {
        $user = $this->user->findOrFail($id);

        if (!empty($data['password'])) {
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
                $this->deleteStoredProfileImage($currentImage);
                $profileImage = $this->uploadProfileImageToCloudinary($data['profile_image']);
            } else {
                $resolved = $this->resolveProfileImageValue($data['profile_image']);

                if ($this->profileImagesAreDifferent($currentImage, $resolved)) {
                    $this->deleteStoredProfileImage($currentImage);
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
            $this->deleteStoredProfileImage($user->userInformation->profile_image);
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

    private function resolveProfileImageValue(mixed $value): ?string
    {
        if ($value instanceof UploadedFile) {
            return $this->uploadProfileImageToCloudinary($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function uploadProfileImageToCloudinary(UploadedFile $file): string
    {
        $result = $this->cloudinary->upload($file, ['folder' => 'user_profiles']);

        return $result['secure_url'];
    }

    private function profileImagesAreDifferent(?string $current, ?string $incoming): bool
    {
        return $this->normalizeProfileImageReference($current)
            !== $this->normalizeProfileImageReference($incoming);
    }

    private function normalizeProfileImageReference(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return MediaUrl::resolvePublic($value) ?? $value;
    }

    private function deleteStoredProfileImage(?string $value): void
    {
        if (! $value) {
            return;
        }

        if (MediaUrl::isRemote($value) && str_contains($value, 'res.cloudinary.com')) {
            $publicId = $this->cloudinary->publicIdFromUrl($value);

            if ($publicId) {
                $this->cloudinary->delete($publicId, 'image');
            }

            return;
        }

        MediaUrl::deleteLocalIfStored($value);
    }
}
