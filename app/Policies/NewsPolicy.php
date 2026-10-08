<?php

namespace App\Policies;

use App\Models\News;
use App\Models\User;

/**
 * Looking at the news list takes the `news.view` permission; adding, changing or deleting an
 * article takes `news.manage` (which includes viewing).
 */
class NewsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('news.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('news.manage');
    }

    public function update(User $user, News $news): bool
    {
        return $user->hasPermission('news.manage');
    }

    public function delete(User $user, News $news): bool
    {
        return $user->hasPermission('news.manage');
    }
}
