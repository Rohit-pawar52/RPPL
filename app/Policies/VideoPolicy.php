<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

/**
 * Looking at the video list takes the `videos.view` permission; adding, changing or deleting a
 * video takes `videos.manage` (which includes viewing).
 */
class VideoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('videos.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('videos.manage');
    }

    public function update(User $user, Video $video): bool
    {
        return $user->hasPermission('videos.manage');
    }

    public function delete(User $user, Video $video): bool
    {
        return $user->hasPermission('videos.manage');
    }
}
