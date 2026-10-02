<?php

namespace App\Services\Registration;

use App\Models\Player;

/**
 * "Does this phone/email already belong to a Player, and do they agree" —
 * the rule PlayerRegistrationImportService applies to a CSV in its simple
 * column format. Deliberately tiny: matching by phone/email independently
 * and flagging a same-row conflict is the entire concern — everything else
 * (how a conflict/inactive/duplicate-registration result is reported)
 * stays in the caller's own code.
 *
 * The public registration form and a Google Form sheet do NOT use it: there
 * the mobile number alone identifies a person and an email is only contact
 * information (often shared by a family or a whole team), so it must never
 * merge two people or block a registration — see
 * GuestPlayerRegistrationService and PlayerRegistrationImportService.
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
