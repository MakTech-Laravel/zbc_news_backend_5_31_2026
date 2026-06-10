<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use App\Models\Article;


class UserService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly User $user,
        private readonly Activity $activity,
        private readonly Article $article
    ) {}

    public function getAllUsers()
    {
        return $this->user->latest()->get();
    }

    public function getUserById($id)
    {
        return $this->user->find($id);
    }

    public function create(array $data)
    {

        $avatarPath = null;
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $avatarPath = $data['avatar']->store('avatars', 'public');
        }

        $user = $this->user->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'avatar' => $avatarPath,
        ]);

        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        }

        return $user;
    }

    public function updateUser($id, array $data): User
    {
        $user = $this->user->findOrFail($id);

        // Avatar handle
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $data['avatar']->store('avatars', 'public');
        } else {
            unset($data['avatar']);
        }

        // Password handle
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        // Role update
        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user->fresh();
    }

    // UserService.php

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

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
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
