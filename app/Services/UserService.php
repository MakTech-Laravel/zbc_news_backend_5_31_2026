<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use App\Models\Article;
use App\Models\UserInformation;
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
        private readonly UserInformation $userInformation
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
            'password' => bcrypt($data['password']),
        ]);

        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        }

        $this->userInformation->create([
            'user_id' => $user->id,
            'profile_image' => isset($data['profile_image']) && $data['profile_image'] instanceof UploadedFile
                ? $data['profile_image']->store('user_profiles', 'public')
                : null,
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

        if (isset($data['profile_image']) && $data['profile_image'] instanceof UploadedFile) {
            $existingImage = $user->userInformation?->profile_image;
            if ($existingImage && Storage::disk('public')->exists($existingImage)) {
                Storage::disk('public')->delete($existingImage);
            }
            $profileImage = $data['profile_image']->store('user_profiles', 'public');
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

        if ($user->userInformation && $user->userInformation->profile_image && Storage::disk('public')->exists($user->userInformation->profile_image)) {
            Storage::disk('public')->delete($user->userInformation->profile_image);
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
