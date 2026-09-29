<?php

namespace App\Services\Registration;

use App\Jobs\ProcessPaymentProofOcr;
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
     *
     * These are translation KEYS (lang/{en,hi}/registration.php), resolved
     * with __() at throw time so the guest sees them in their public
     * site language.
     */
    public const GENERIC_REJECTION_MESSAGE_KEY = 'registration.errors.generic_rejection';

    public const DUPLICATE_MESSAGE_KEY = 'registration.errors.duplicate';

    public const CLOSED_MESSAGE_KEY = 'registration.errors.closed';

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
            throw ValidationException::withMessages(['phone' => __(self::GENERIC_REJECTION_MESSAGE_KEY)]);
        }

        $player = $resolved['player'];

        if ($player && ! $player->is_active) {
            throw ValidationException::withMessages(['phone' => __(self::GENERIC_REJECTION_MESSAGE_KEY)]);
        }

        if ($player && PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->exists()) {
            throw ValidationException::withMessages(['phone' => __(self::DUPLICATE_MESSAGE_KEY)]);
        }

        // Files are not transactional — stored before the DB transaction,
        // explicitly cleaned up below if anything after this point fails.
        // Never touches any file belonging to an existing registration.
        $aadhaarPath = $aadhaar->store(self::AADHAAR_DIRECTORY, self::DISK);
        $paymentProofPath = $paymentProof->store(self::PAYMENT_PROOF_DIRECTORY, self::DISK);

        try {
            $registration = DB::transaction(function () use ($edition, $data, $phone, $email, $player, $aadhaarPath, $paymentProofPath) {
                // Authoritative re-check, locked: registration_open/
                // status/fee may have changed between GET and this POST,
                // or even between the pre-check above and right now.
                $lockedEdition = Edition::whereKey($edition->id)->lockForUpdate()->firstOrFail();

                if (! $lockedEdition->isAcceptingPublicRegistration()) {
                    throw ValidationException::withMessages(['phone' => __(self::CLOSED_MESSAGE_KEY)]);
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

        // Dispatched only now that the transaction above has actually
        // committed — a worker must never be able to pick up a job
        // referencing a row that doesn't exist yet. Deliberately a
        // SEPARATE try/catch from the one above: a queue-connection
        // failure here must never delete the just-stored files or throw
        // in place of the registration that has already, successfully,
        // been created. OCR is best-effort post-commit work only (see
        // ProcessPaymentProofOcr) — it can never affect this response.
        try {
            ProcessPaymentProofOcr::dispatch($registration);
        } catch (Throwable $e) {
            report($e);
        }

        return $registration;
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
