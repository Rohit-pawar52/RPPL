<?php

namespace App\Policies;

use App\Models\EditionTeam;
use App\Models\User;

/**
 * Resource-specific authorization for Edition Team (team participation)
 * management. Mirrors the other admin policies: only the admin role may
 * manage this — scorers never get any of these abilities.
 *
 * No update() ability: EditionTeam has no editable attributes beyond its
 * own identity (edition_id, team_id) — see the Phase 3.7 report.
 */
class EditionTeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, EditionTeam $editionTeam): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, EditionTeam $editionTeam): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
