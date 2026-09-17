<?php

namespace App\Services\Registration;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports a fixed-format CSV (as exported by a Google Form/Sheet) into
 * Players + PlayerRegistrations for one edition. Two-pass by design:
 * pass 1 parses/validates/resolves identity with zero writes, and only
 * if that pass finds no fatal problem does the write pass run, inside
 * one transaction. This guarantees a bad file never partially imports.
 *
 * Import is create-only: an existing Player's fields are never
 * overwritten from the CSV, and an existing PlayerRegistration for this
 * edition is never updated — see resolveRow()'s "already registered"
 * handling. Finance (EditionTransaction) is deliberately untouched,
 * exactly as Phase 3.25 established.
 */
class PlayerRegistrationImportService
{
    /**
     * Comfortably above RPPL's real scale (~150 registrations/edition).
     */
    private const MAX_ROWS = 1000;

    public function __construct(private readonly PlayerIdentityResolver $identity) {}

    /**
     * @return array{success: bool, created_registrations: int, created_players: int, skipped: int, errors: list<string>}
     */
    public function import(Edition $edition, UploadedFile $file): array
    {
        $rows = $this->parseCsv($file);

        if ($rows === null) {
            return $this->failure(['The CSV must include a "name" column.']);
        }

        if (count($rows) === 0) {
            return $this->failure(['No registration rows were found.']);
        }

        if (count($rows) > self::MAX_ROWS) {
            return $this->failure(['The CSV contains more than '.self::MAX_ROWS.' rows. Split the file and import in smaller batches.']);
        }

        $plan = $this->buildPlan($edition, $rows);

        if (! empty($plan['errors'])) {
            return $this->failure($plan['errors']);
        }

        return $this->writePlan($edition, $plan['actions'], $plan['skipped']);
    }

    /**
     * Parses the CSV into an array of associative row arrays keyed by
     * normalized column name. Returns null when the required "name"
     * header is missing (a structural problem, checked before any row
     * is even looked at). Genuinely empty rows are dropped here rather
     * than treated as validation errors.
     *
     * @return list<array<string, string|null>>|null
     */
    private function parseCsv(UploadedFile $file): ?array
    {
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle) ?: [];

        // Strip a UTF-8 BOM that may prefix the first header cell.
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $normalizedHeader = array_map(fn ($column) => $this->normalizeHeader((string) $column), $header);

        if (! in_array('name', $normalizedHeader, true)) {
            fclose($handle);

            return null;
        }

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($data)) {
                continue;
            }

            $row = [];

            foreach ($normalizedHeader as $index => $key) {
                if ($key === '') {
                    continue;
                }

                $value = $data[$index] ?? null;
                $row[$key] = $value === null ? null : trim($value);
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(string $value): string
    {
        return strtolower(str_replace(' ', '_', trim($value)));
    }

    private function isBlankRow(array $data): bool
    {
        return array_filter($data, fn ($value) => trim((string) $value) !== '') === [];
    }

    /**
     * Pass 1: validates every row and resolves Player identity, without
     * writing anything. Only two outcomes are non-fatal (counted as
     * "skipped"): a match already registered for this edition, and a
     * duplicate identity appearing more than once in this same CSV.
     * Every other problem (missing name, invalid payment status/fee/
     * date, an email/phone identity conflict, an inactive matched
     * player) is fatal — its message is collected and, if any exist,
     * the whole import is rejected before pass 2 ever runs.
     *
     * @param  list<array<string, string|null>>  $rows
     * @return array{errors: list<string>, actions: list<array<string, mixed>>, skipped: int}
     */
    private function buildPlan(Edition $edition, array $rows): array
    {
        $errors = [];
        $actions = [];
        $skipped = 0;
        $seenIdentities = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for the header row, +1 for 1-based numbering

            $name = $this->blank($row['name'] ?? null);
            $phone = $this->blank($row['phone'] ?? null);
            $email = $this->blank($row['email'] ?? null);
            $feeRaw = $this->blank($row['registration_fee'] ?? null);
            $statusRaw = $this->blank($row['payment_status'] ?? null);
            $registeredAtRaw = $this->blank($row['registered_at'] ?? null);

            if ($name === null) {
                $errors[] = "Row {$rowNumber}: name is required.";

                continue;
            }

            // Same default the database column itself uses for a
            // manually-created registration with no status chosen.
            $paymentStatus = $statusRaw ?? 'pending';

            if (! in_array($paymentStatus, PlayerRegistration::PAYMENT_STATUSES, true)) {
                $errors[] = "Row {$rowNumber}: invalid payment status \"{$statusRaw}\".";

                continue;
            }

            $registrationFee = null;

            if ($feeRaw !== null) {
                if (! is_numeric($feeRaw) || (float) $feeRaw < 0 || (float) $feeRaw > 99999999.99) {
                    $errors[] = "Row {$rowNumber}: invalid registration fee.";

                    continue;
                }

                $registrationFee = round((float) $feeRaw, 2);
            }

            $registeredAt = null;

            if ($registeredAtRaw !== null) {
                try {
                    $registeredAt = Carbon::parse($registeredAtRaw);
                } catch (Throwable) {
                    $errors[] = "Row {$rowNumber}: invalid registered_at date.";

                    continue;
                }
            }

            // Same core match/conflict logic the public guest
            // registration flow uses (PlayerIdentityResolver) — never a
            // second, independently-drifting identity algorithm.
            $resolved = $this->identity->resolve($phone, $email);

            if ($resolved['conflict']) {
                $errors[] = "Row {$rowNumber}: email and phone belong to different players.";

                continue;
            }

            $existingPlayer = $resolved['player'];

            if ($existingPlayer && ! $existingPlayer->is_active) {
                $errors[] = "Row {$rowNumber}: matched player is inactive and cannot be registered.";

                continue;
            }

            if ($existingPlayer) {
                $alreadyRegistered = PlayerRegistration::where('edition_id', $edition->id)
                    ->where('player_id', $existingPlayer->id)
                    ->exists();

                if ($alreadyRegistered) {
                    $skipped++;

                    continue;
                }
            }

            // Identity used to catch the SAME person appearing twice in
            // this CSV. A row with neither email nor phone can never be
            // proven to be a duplicate of another such row, so each
            // gets its own key (never collapsed together).
            $identityKey = $existingPlayer
                ? 'player:'.$existingPlayer->id
                : 'new:'.($email ?? $phone ?? 'row:'.$rowNumber);

            if (isset($seenIdentities[$identityKey])) {
                $skipped++;

                continue;
            }

            $seenIdentities[$identityKey] = true;

            $actions[] = [
                'existing_player_id' => $existingPlayer?->id,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'payment_status' => $paymentStatus,
                'registration_fee' => $registrationFee,
                'registered_at' => $registeredAt,
            ];
        }

        return ['errors' => $errors, 'actions' => $actions, 'skipped' => $skipped];
    }

    /**
     * Pass 2: only reached when pass 1 found zero fatal problems.
     * Creates any needed Players and every planned PlayerRegistration
     * inside one transaction, so a rare unique-constraint race (two
     * imports resolving the same identity concurrently) rolls back the
     * entire file rather than leaving it half-imported.
     *
     * @param  list<array<string, mixed>>  $actions
     * @return array{success: bool, created_registrations: int, created_players: int, skipped: int, errors: list<string>}
     */
    private function writePlan(Edition $edition, array $actions, int $skipped): array
    {
        $createdPlayers = 0;
        $createdRegistrations = 0;

        try {
            DB::transaction(function () use ($edition, $actions, &$createdPlayers, &$createdRegistrations) {
                foreach ($actions as $action) {
                    $playerId = $action['existing_player_id'];

                    if ($playerId === null) {
                        $player = Player::create([
                            'name' => $action['name'],
                            'phone' => $action['phone'],
                            'email' => $action['email'],
                        ]);

                        $playerId = $player->id;
                        $createdPlayers++;
                    }

                    // registration_number is NOT NULL/UNIQUE and not
                    // fillable (never guest/CSV-controlled) — see
                    // PlayerRegistrationService::createRegistration()
                    // for the identical placeholder-then-assign pattern
                    // and why it's needed.
                    $registration = new PlayerRegistration([
                        'edition_id' => $edition->id,
                        'player_id' => $playerId,
                        'payment_status' => $action['payment_status'],
                        'registration_fee' => $action['registration_fee'],
                        'registered_at' => $action['registered_at'],
                    ]);
                    $registration->registration_number = (string) Str::uuid();
                    $registration->save();
                    $registration->assignRegistrationNumber();

                    $createdRegistrations++;
                }
            });
        } catch (QueryException) {
            return $this->failure(['The import could not be completed because of a data conflict. Please retry.']);
        }

        return [
            'success' => true,
            'created_registrations' => $createdRegistrations,
            'created_players' => $createdPlayers,
            'skipped' => $skipped,
            'errors' => [],
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return array{success: bool, created_registrations: int, created_players: int, skipped: int, errors: list<string>}
     */
    private function failure(array $errors): array
    {
        return [
            'success' => false,
            'created_registrations' => 0,
            'created_players' => 0,
            'skipped' => 0,
            'errors' => $errors,
        ];
    }

    private function blank(?string $value): ?string
    {
        return $value === '' || $value === null ? null : $value;
    }
}
