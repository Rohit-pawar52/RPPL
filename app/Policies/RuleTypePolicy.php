<?php

namespace App\Policies;

use App\Models\RuleType;
use App\Models\User;

class RuleTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, RuleType $ruleType): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, RuleType $ruleType): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
