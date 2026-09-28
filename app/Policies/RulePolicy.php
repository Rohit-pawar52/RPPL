<?php

namespace App\Policies;

use App\Models\Rule;
use App\Models\User;

class RulePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Rule $rule): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Rule $rule): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
