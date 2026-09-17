<?php

namespace App\Services\Registration;

use App\Models\Player;

/**
 * The one shared core of "does this phone/email already belong to a
 * Player, and do they agree" — used by both PlayerRegistrationImportService
 * (CSV import) and the public guest registration flow (Phase 3.39C), so
 * the two never develop contradictory identity rules. Deliberately tiny:
 * matching by phone/email independently and flagging a same-row
 * conflict is the entire shared concern — everything else (how a
 * conflict/inactive/duplicate-registration result is reported to an
 * admin importing a file vs. a public guest) legitimately differs per
 * caller and stays in each caller's own code.
 */
class PlayerIdentityResolver
{
    /**
     * $phone/$email are expected to already be in whatever normalized
     * form the caller stores/compares (see Player::normalizePhone()/
     * normalizeEmail() for the guest flow) — this class does no
     * normalization itself, only matching.
     *
     * @return array{player: Player|null, conflict: bool}
     */
    public function resolve(?string $phone, ?string $email): array
    {
        $byPhone = $phone ? Player::where('phone', $phone)->first() : null;
        $byEmail = $email ? Player::where('email', $email)->first() : null;

        if ($byPhone && $byEmail && $byPhone->id !== $byEmail->id) {
            return ['player' => null, 'conflict' => true];
        }

        return ['player' => $byPhone ?: $byEmail, 'conflict' => false];
    }
}
