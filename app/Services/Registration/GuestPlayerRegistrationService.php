<?php

namespace App\Services\Registration;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Orchestrates one public/guest registration submission (Phase 3.39C):
 * identity resolution (reusing PlayerIdentityResolver — the same core
 * logic CSV import uses), private document storage, and registration
 * creation (reusing PlayerRegistrationService::createRegistration() —
 * the same mechanism admin-created registrations use, so
 * registration_number generation never has a second implementation).
 *
 * Guest submission can only ever CREATE a Player, never modify an
 * existing one — see registerNewOrExisting()'s docblock.
 */
class GuestPlayerRegistrationService
{
    private const DISK = 'local';

    private const AADHAAR_DIRECTORY = 'player-registrations/aadhaar';

    private const PAYMENT_PROOF_DIRECTORY = 'player-registrations/payment-proofs';

    /**
     * Deliberately generic — never reveals which field conflicted, that
     * a phone/email belongs to someone else, or that a matched Player is
     * inactive. See Phase 3.39C's privacy requirements.
     */
    public const GENERIC_REJECTION_MESSAGE = "We couldn't process the registration with the details provided. Please contact RPPL administration.";

    public const DUPLICATE_MESSAGE = 'A registration already exists for these details. Please use the registration status option or contact RPPL administration.';

    public const CLOSED_MESSAGE = 'Player registration is currently closed.';

    public function __construct(
        private readonly PlayerIdentityResolver $identity,
        private readonly PlayerRegistrationService $registrations,
    ) {}

    /**
     * @param  array{name: string, phone: string, email: ?string, date_of_birth: string, primary_role: string}  $data
     */
    public function register(Edition $edition, array $data, UploadedFile $aadhaar, UploadedFile $paymentProof): PlayerRegistration
    {
        $phone = Player::normalizePhone($data['phone']);
        $email = Player::normalizeEmail($data['email'] ?? null);

        $resolved = $this->identity->resolve($phone, $email);

        if ($resolved['conflict']) {
            throw ValidationException::withMessages(['phone' => self::GENERIC_REJECTION_MESSAGE]);
        }

        $player = $resolved['player'];

        if ($player && ! $player->is_active) {
            throw ValidationException::withMessages(['phone' => self::GENERIC_REJECTION_MESSAGE]);
        }

        if ($player && PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->exists()) {
            throw ValidationException::withMessages(['phone' => self::DUPLICATE_MESSAGE]);
        }

        // Files are not transactional — stored before the DB transaction,
        // explicitly cleaned up below if anything after this point fails.
        // Never touches any file belonging to an existing registration.
        $aadhaarPath = $aadhaar->store(self::AADHAAR_DIRECTORY, self::DISK);
        $paymentProofPath = $paymentProof->store(self::PAYMENT_PROOF_DIRECTORY, self::DISK);

        try {
            return DB::transaction(function () use ($edition, $data, $phone, $email, $player, $aadhaarPath, $paymentProofPath) {
                // Authoritative re-check, locked: registration_open/
                // status/fee may have changed between GET and this POST,
                // or even between the pre-check above and right now.
                $lockedEdition = Edition::whereKey($edition->id)->lockForUpdate()->firstOrFail();

                if (! $lockedEdition->isAcceptingPublicRegistration()) {
                    throw ValidationException::withMessages(['phone' => self::CLOSED_MESSAGE]);
                }

                $playerId = $player?->id ?? $this->createPlayer($data, $phone, $email)->id;

                return $this->registrations->createRegistration([
                    'edition_id' => $lockedEdition->id,
                    'player_id' => $playerId,
                    'payment_status' => 'pending',
                    'registration_fee' => $lockedEdition->registration_fee,
                    'registered_at' => now(),
                    'aadhaar_document_path' => $aadhaarPath,
                    'payment_proof_path' => $paymentProofPath,
                ]);
            });
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete([$aadhaarPath, $paymentProofPath]);

            throw $e;
        }
    }

    /**
     * Guest submission may only ever CREATE a brand-new Player identity
     * — an existing Player's name/phone/email/date_of_birth/primary_role
     * are NEVER updated from guest input, even when a field is
     * currently null. This is the one easy-to-reason-about invariant:
     * the public form cannot become an unauthenticated profile-edit
     * endpoint. Any correction/enrichment of an existing Player remains
     * a manual admin action.
     *
     * Handles the same-phone/email concurrent-new-submission race
     * (two guests, previously-unseen identical phone, near-simultaneous
     * requests) without a lock: if Player::create() hits the phone/email
     * UNIQUE constraint, re-resolve identity fresh and reuse whichever
     * Player now genuinely matches — never blindly assume "the race
     * means it's the same submitter" without checking.
     */
    private function createPlayer(array $data, ?string $phone, ?string $email): Player
    {
        try {
            return Player::create([
                'name' => $data['name'],
                'phone' => $phone,
                'email' => $email,
                'date_of_birth' => $data['date_of_birth'],
                'primary_role' => $data['primary_role'],
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            $reResolved = $this->identity->resolve($phone, $email);

            if ($reResolved['player'] === null || $reResolved['conflict']) {
                throw $e;
            }

            return $reResolved['player'];
        }
    }
}
