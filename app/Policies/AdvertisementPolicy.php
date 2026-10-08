<?php

namespace App\Policies;

use App\Models\Advertisement;
use App\Models\User;

/**
 * Looking at the sponsor advertisements takes the `advertisements.view` permission; adding,
 * changing or deleting one takes `advertisements.manage` (which includes viewing).
 */
class AdvertisementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('advertisements.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('advertisements.manage');
    }

    public function update(User $user, Advertisement $advertisement): bool
    {
        return $user->hasPermission('advertisements.manage');
    }

    public function delete(User $user, Advertisement $advertisement): bool
    {
        return $user->hasPermission('advertisements.manage');
    }
}
