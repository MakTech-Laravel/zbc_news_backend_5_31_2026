<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly User $user
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

    public function deleteUser($id): bool
    {
        $user = $this->user->findOrFail($id);

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $user->syncRoles([]);

        return $user->delete();
    }
}
