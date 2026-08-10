<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Media $media): bool
    {
        // Ready assets are shared across the newsroom; drafts stay with the uploader.
        return $media->status === 'ready'
            || $media->uploaded_by === $user->id
            || $user->hasRole(['super_admin', 'admin']);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Media $media): bool
    {
        return $media->uploaded_by === $user->id
            || $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, Media $media): bool
    {
        return $media->uploaded_by === $user->id
            || $user->hasRole(['super_admin', 'admin']);
    }
}
