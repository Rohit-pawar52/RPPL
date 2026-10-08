<?php

namespace App\Policies;

use App\Models\RuleType;
use App\Models\User;

/**
 * Rule types are part of the rules section, so they share its permissions: looking at them takes
 * `rules.view`; adding, changing or deleting one takes `rules.manage` (which includes viewing).
 */
class RuleTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('rules.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('rules.manage');
    }

    public function update(User $user, RuleType $ruleType): bool
    {
        return $user->hasPermission('rules.manage');
    }

    public function delete(User $user, RuleType $ruleType): bool
    {
        return $user->hasPermission('rules.manage');
    }
}
