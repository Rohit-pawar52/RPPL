<?php

namespace App\Services\Registration;

use App\Models\PlayerRegistration;
use Illuminate\Database\Eloquent\Collection;

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
     * The registrations of the player who owns $normalizedPhone — all
     * editions, newest first — or, when a registration number is also
     * given, only that one (it must belong to that phone). The phone alone
     * is enough because a player who lost the registration number must
     * still be able to see where they stand; the number is an optional
     * second credential that unlocks the full name (see $showFullName in
     * the result).
     *
     * @param  ?string  $registrationNumber  already trimmed/uppercased (see StatusLookupPlayerRegistrationRequest)
     * @param  string  $normalizedPhone  already run through Player::normalizePhone()
     * @return Collection<int, PlayerRegistration>
     */
    public function lookup(?string $registrationNumber, string $normalizedPhone): Collection
    {
        return PlayerRegistration::query()
            ->select(['id', 'registration_number', 'edition_id', 'player_id', 'payment_status', 'registration_fee', 'registered_at'])
            ->when($registrationNumber, fn ($query, $number) => $query->where('registration_number', $number))
            ->whereHas('player', fn ($query) => $query->where('phone', $normalizedPhone))
            ->with(['player:id,name', 'edition:id,name,year'])
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * "Ramesh Joshi" -> "R**** J****": enough for the right person to
     * recognise themselves, not enough to read a stranger's name off a
     * phone number someone merely knows. A fixed number of stars, so the
     * name's length is not revealed either.
     */
    public static function maskName(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn (string $word) => mb_substr($word, 0, 1).'****', $words));
    }
}
