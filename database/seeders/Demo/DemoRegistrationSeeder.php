<?php

namespace Database\Seeders\Demo;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use App\Services\TeamPlayer\TeamPlayerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Registers the DemoPlayerSeeder player pool into the historical and
 * active editions, and builds each participating team's squad for those
 * two editions. The upcoming (draft) edition deliberately gets no
 * registrations yet — realistic for a season that hasn't opened.
 *
 * Registrations are created via PlayerRegistrationService::
 * createRegistration() — the SAME lower-level service the public guest
 * flow's GuestPlayerRegistrationService wraps — never
 * GuestPlayerRegistrationService::register() itself, because that method
 * dispatches ProcessPaymentProofOcr after commit, and this phase
 * explicitly forbids seeders from ever dispatching an OCR job. Using
 * createRegistration() directly still exercises the real
 * assignRegistrationNumber() mechanism (RPPL-{year}-{id}), so
 * registration numbers are never hand-crafted.
 *
 * No seeded registration has an aadhaar_document_path — a synthetic
 * Aadhaar-shaped placeholder was judged too close to "could be mistaken
 * for a real ID" territory, and this machine has neither GD nor Imagick
 * available to bake a clearly-labeled "DEMO DOCUMENT" placeholder image
 * onto one, so that slot is deliberately left empty rather than forced.
 * Exactly ONE registration (the first squad slot of the active edition,
 * a 'paid' registration — see attachDemoPaymentProof()) gets a
 * payment_proof_path pointing at a hand-built, non-photographic,
 * solid-color placeholder PNG (database/seeders/Demo/assets/
 * demo-payment-proof.png — a plain two-tone rectangle, no text, no
 * photo, no identity data of any kind), so the admin document-review
 * screen has at least one real file to view/download. It is stored
 * through the exact same disk ('local') and directory convention
 * ('player-registrations/payment-proofs') the real guest upload flow
 * uses (see GuestPlayerRegistrationService::PAYMENT_PROOF_DIRECTORY),
 * never an invented path scheme. Every other registration still has no
 * payment_proof_path, so — consistent with what
 * ProcessPaymentProofOcr::handle() itself would set for a registration
 * with no payment proof — ocr_status is explicitly set to OCR_FAILED
 * right after creation, rather than left at the migration's 'pending'
 * default, which would misleadingly suggest a job is still queued to
 * process it. (The one demo registration with a payment proof keeps its
 * OCR_FAILED status too — it was never actually run through
 * ProcessPaymentProofOcr, so pretending otherwise would misrepresent
 * what happened.)
 */
class DemoRegistrationSeeder extends Seeder
{
    /**
     * Mirrors GuestPlayerRegistrationService::DISK/
     * PAYMENT_PROOF_DIRECTORY exactly — the demo payment-proof file must
     * live under the very same disk and directory convention a real
     * guest upload would use, never an invented path scheme.
     */
    private const PAYMENT_PROOF_DISK = 'local';

    private const PAYMENT_PROOF_DIRECTORY = 'player-registrations/payment-proofs';

    private const DEMO_PAYMENT_PROOF_ASSET = __DIR__.'/assets/demo-payment-proof.png';

    public function __construct(
        private readonly PlayerRegistrationService $registrations,
        private readonly TeamPlayerService $squads,
    ) {}

    public function run(): void
    {
        $players = Player::orderBy('id')->get()->values();

        $historical = Edition::where('year', DemoEditionSeeder::HISTORICAL_YEAR)->firstOrFail();
        $active = Edition::where('year', DemoEditionSeeder::ACTIVE_YEAR)->firstOrFail();

        $historicalTeams = EditionTeam::where('edition_id', $historical->id)->orderBy('id')->get();
        $activeTeams = EditionTeam::where('edition_id', $active->id)->orderBy('id')->get();

        $this->seedHistoricalEdition($historical, $historicalTeams, $players);
        $this->seedActiveEdition($active, $activeTeams, $players);
    }

    /**
     * 4 teams x 6 players = squads for players[0..23], all paid except
     * one refunded (realistic wrap-up variety for a completed season).
     *
     * The `! $registration->teamPlayer()->exists()` guard around
     * createTeamPlayer() (here and in seedActiveEdition()) makes a
     * second run of this seeder idempotent end-to-end: register() alone
     * already returns the existing row for an already-seeded player, but
     * without this guard a rerun would still try to insert the very
     * same team_players row a second time and fail loudly on the unique
     * jersey/squad-slot constraint.
     */
    private function seedHistoricalEdition(Edition $edition, $editionTeams, $players): void
    {
        $playerIndex = 0;

        foreach ($editionTeams as $editionTeam) {
            for ($jersey = 1; $jersey <= 6; $jersey++) {
                $player = $players[$playerIndex];
                $isLast = $playerIndex === 23;

                $registration = $this->register($edition, $player, $isLast ? 'refunded' : 'paid', monthsAgo: 14);

                if (! $registration->teamPlayer()->exists()) {
                    $this->squads->createTeamPlayer([
                        'edition_team_id' => $editionTeam->id,
                        'player_registration_id' => $registration->id,
                        'jersey_number' => $jersey,
                        'role' => $player->primary_role,
                    ]);
                }

                $playerIndex++;
            }
        }
    }

    /**
     * 6 teams x 7 players = squads for players[0..41] (paid), plus 3 more
     * registrations (players[42..44]) left unsquadded to demonstrate
     * pending/failed/refunded payment variety on the currently-open
     * edition. The very first registration created here (team 1, jersey
     * 1 — a deterministic, always-the-same-row pick across reruns) is
     * also the one demo registration that gets a payment-proof document
     * attached — see attachDemoPaymentProof()'s docblock and the class
     * docblock above for why.
     */
    private function seedActiveEdition(Edition $edition, $editionTeams, $players): void
    {
        $playerIndex = 0;
        $demoDocumentRegistration = null;

        foreach ($editionTeams as $editionTeam) {
            for ($jersey = 1; $jersey <= 7; $jersey++) {
                $player = $players[$playerIndex];

                $registration = $this->register($edition, $player, 'paid', monthsAgo: 2);

                $demoDocumentRegistration ??= $registration;

                if (! $registration->teamPlayer()->exists()) {
                    $this->squads->createTeamPlayer([
                        'edition_team_id' => $editionTeam->id,
                        'player_registration_id' => $registration->id,
                        'jersey_number' => $jersey,
                        'role' => $player->primary_role,
                    ]);
                }

                $playerIndex++;
            }
        }

        $this->register($edition, $players[42], 'pending', monthsAgo: 1);
        $this->register($edition, $players[43], 'failed', monthsAgo: 1);
        $this->register($edition, $players[44], 'refunded', monthsAgo: 1);

        $this->attachDemoPaymentProof($demoDocumentRegistration);
    }

    /**
     * Attaches the synthetic placeholder PNG (see class docblock) to
     * exactly one demo registration, through the SAME
     * Storage::disk('local')->put()-style write the real upload flow
     * uses — just with a hand-built local asset instead of an
     * UploadedFile. Idempotent: a second run of this seeder finds the
     * column already pointing at an existing physical file and does
     * nothing, so re-running `db:seed` never duplicates files or errors.
     */
    private function attachDemoPaymentProof(PlayerRegistration $registration): void
    {
        if (
            $registration->payment_proof_path !== null
            && Storage::disk(self::PAYMENT_PROOF_DISK)->exists($registration->payment_proof_path)
        ) {
            return;
        }

        $path = self::PAYMENT_PROOF_DIRECTORY."/demo-registration-{$registration->id}-payment-proof.png";

        Storage::disk(self::PAYMENT_PROOF_DISK)->put(
            $path,
            file_get_contents(self::DEMO_PAYMENT_PROOF_ASSET)
        );

        $registration->update(['payment_proof_path' => $path]);
    }

    private function register(Edition $edition, Player $player, string $paymentStatus, int $monthsAgo): PlayerRegistration
    {
        $existing = PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->first();

        if ($existing) {
            return $existing;
        }

        $registration = $this->registrations->createRegistration([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => $paymentStatus,
            'registration_fee' => $edition->registration_fee ?? 500.00,
            'registered_at' => now()->subMonths($monthsAgo),
        ]);

        $registration->update(['ocr_status' => PlayerRegistration::OCR_FAILED]);

        return $registration;
    }
}
