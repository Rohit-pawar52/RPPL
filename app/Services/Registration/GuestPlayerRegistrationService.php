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
 * identity resolution, private file storage, and registration creation
 * (reusing PlayerRegistrationService::createRegistration() — the same
 * mechanism admin-created registrations use, so registration_number
 * generation never has a second implementation).
 *
 * Guest submission can only ever CREATE a Player, never modify an
 * existing one — see createPlayer()'s docblock. What the player answered
 * for THIS edition (age, village, tehsil, district, the UTR they typed)
 * therefore lives on the registration, so it is kept even when the player
 * already existed.
 */
class GuestPlayerRegistrationService
{
    private const DISK = 'local';

    private const PHOTO_DIRECTORY = 'player-registrations/photos';

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
        private readonly PlayerRegistrationService $registrations,
    ) {}

    /**
     * $data is the validated form (see StorePublicPlayerRegistrationRequest):
     * name, phone, email (optional), age, primary_role, batting_style,
     * bowling_style, village, tehsil, district, submitted_utr.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(Edition $edition, array $data, UploadedFile $photo, UploadedFile $paymentProof): PlayerRegistration
    {
        $phone = Player::normalizePhone($data['phone']);
        $email = Player::normalizeEmail($data['email'] ?? null);

        // The mobile number IS the identity: it is required, unique per
        // player, and the one thing a registrant has to give correctly to
        // look their registration up later. The email is only contact
        // information (often shared, often blank) — it never decides who
        // someone is, so an address that already belongs to another player
        // can neither merge two people nor block a registration.
        $player = Player::where('phone', $phone)->first();

        if ($player && ! $player->is_active) {
            throw ValidationException::withMessages(['phone' => __(self::GENERIC_REJECTION_MESSAGE_KEY)]);
        }

        if ($player && PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->exists()) {
            throw ValidationException::withMessages(['phone' => __(self::DUPLICATE_MESSAGE_KEY)]);
        }

        // Files are not transactional — stored before the DB transaction,
        // explicitly cleaned up below if anything after this point fails.
        // Never touches any file belonging to an existing registration.
        $photoPath = $photo->store(self::PHOTO_DIRECTORY, self::DISK);
        $paymentProofPath = $paymentProof->store(self::PAYMENT_PROOF_DIRECTORY, self::DISK);

        try {
            $registration = DB::transaction(function () use ($edition, $data, $phone, $email, $player, $photoPath, $paymentProofPath) {
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
                    'age' => $data['age'],
                    'village' => $data['village'],
                    'tehsil' => $data['tehsil'],
                    'district' => $data['district'],
                    'submitted_utr' => $data['submitted_utr'],
                    'photo_path' => $photoPath,
                    'payment_proof_path' => $paymentProofPath,
                ]);
            });
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete([$photoPath, $paymentProofPath]);

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
     * — an existing Player's name/phone/email/role/hands are NEVER
     * updated from guest input, even when a field is currently null. This
     * is the one easy-to-reason-about invariant: the public form cannot
     * become an unauthenticated profile-edit endpoint. Any correction/
     * enrichment of an existing Player remains a manual admin action.
     *
     * players.email is unique, so a new player only gets the address when
     * no one else has it (the registration goes ahead either way). The
     * same-phone concurrent-new-submission race (two guests, previously-
     * unseen identical phone, near-simultaneous requests) is handled
     * without a lock: if Player::create() hits a UNIQUE constraint, the
     * player that now owns the phone is reused, or — when it was only the
     * email that collided — the player is created without it.
     *
     * @param  array<string, mixed>  $data
     */
    private function createPlayer(array $data, string $phone, ?string $email): Player
    {
        $attributes = [
            'name' => $data['name'],
            'phone' => $phone,
            'email' => $email !== null && ! Player::where('email', $email)->exists() ? $email : null,
            'primary_role' => $data['primary_role'],
            'batting_style' => $data['batting_style'],
            'bowling_style' => $data['bowling_style'],
        ];

        try {
            return Player::create($attributes);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            $owner = Player::where('phone', $phone)->first();

            if ($owner !== null) {
                return $owner;
            }

            return Player::create(['email' => null] + $attributes);
        }
    }
}
