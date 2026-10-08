<?php

namespace App\Policies;

use App\Models\Rule;
use App\Models\User;

/**
 * Looking at the rules takes the `rules.view` permission; adding, changing or deleting a rule
 * takes `rules.manage` (which includes viewing). Rule types share the same two permissions, see
 * RuleTypePolicy.
 */
class RulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('rules.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('rules.manage');
    }

    public function update(User $user, Rule $rule): bool
    {
        return $user->hasPermission('rules.manage');
    }

    public function delete(User $user, Rule $rule): bool
    {
        return $user->hasPermission('rules.manage');
    }
}
