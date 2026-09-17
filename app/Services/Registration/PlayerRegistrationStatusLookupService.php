<?php

namespace App\Services\Registration;

use App\Models\PlayerRegistration;

/**
 * Public, read-only registration-status lookup (Phase 3.39E). Both
 * credentials are required in a single query — a registration is NEVER
 * resolved by registration_number alone and then checked against phone
 * afterward, which would let a caller learn "the number exists" from
 * timing/response-shape differences.
 *
 * Deliberately does NOT filter by Player::is_active or by Edition
 * status/openForParticipation(): a historical registration must remain
 * checkable even after the player is later deactivated or the edition
 * completes. Those eligibility rules govern NEW submissions only (see
 * GuestPlayerRegistrationService) and have no place here.
 *
 * Selects only the columns the public result actually needs — never
 * aadhaar_document_path/payment_proof_path/payment_reference, which stay
 * admin-only.
 */
class PlayerRegistrationStatusLookupService
{
    /**
     * @param  string  $registrationNumber  already trimmed/uppercased (see StatusLookupPlayerRegistrationRequest)
     * @param  string  $normalizedPhone  already run through Player::normalizePhone()
     */
    public function lookup(string $registrationNumber, string $normalizedPhone): ?PlayerRegistration
    {
        return PlayerRegistration::query()
            ->select(['id', 'registration_number', 'edition_id', 'player_id', 'payment_status', 'registration_fee', 'registered_at'])
            ->where('registration_number', $registrationNumber)
            ->whereHas('player', fn ($query) => $query->where('phone', $normalizedPhone))
            ->with(['player:id,name', 'edition:id,name,year'])
            ->first();
    }
}
