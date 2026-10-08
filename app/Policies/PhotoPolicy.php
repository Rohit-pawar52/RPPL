<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;

/**
 * Looking at the photo list takes the `photos.view` permission; adding, changing or deleting a
 * photo takes `photos.manage` (which includes viewing).
 */
class PhotoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('photos.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('photos.manage');
    }

    public function update(User $user, Photo $photo): bool
    {
        return $user->hasPermission('photos.manage');
    }

    public function delete(User $user, Photo $photo): bool
    {
        return $user->hasPermission('photos.manage');
    }
}
