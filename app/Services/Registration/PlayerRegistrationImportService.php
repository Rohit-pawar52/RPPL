<?php

namespace App\Services\Registration;

use App\Jobs\FetchRegistrationDriveFiles;
use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Services\Settings\SettingsService;
use App\Support\XlsxReader;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Imports a CSV into Players + PlayerRegistrations for one edition. It
 * understands two shapes: the simple column set the importer always had
 * (name, phone, email, registration_fee, payment_status, registered_at),
 * and a Google Form response sheet exactly as downloaded — its Timestamp,
 * Email Address, Name, Age, Mobile Number, Role, Left hand/right hand,
 * Gram, Tehsil, District, UTR and the two Drive links for the uploaded
 * photo and payment screenshot (see RegistrationImportParser for how the
 * headers and messy answers are read). Files cannot travel in a CSV, so
 * only the Drive links are kept.
 *
 * Two-pass by design: pass 1 parses/validates/resolves identity with zero
 * writes, and only if that pass finds no fatal problem does the write pass
 * run, inside one transaction. A bad file therefore never partially
 * imports, and the same pass 1 powers the "check only" run. Pass 1 is
 * strict about the original columns (status, fee, date) and forgiving about
 * the form's free-text answers: those never fail a row, they are cleaned up
 * and reported as notes so the admin can correct them from the edit page.
 *
 * Import is create-only: an existing Player's fields are never
 * overwritten from the CSV, and an existing PlayerRegistration for this
 * edition is never updated — so importing the same sheet again only adds
 * the new responses. Finance (EditionTransaction) is deliberately
 * untouched, exactly as Phase 3.25 established.
 */
class PlayerRegistrationImportService
{
    /**
     * Comfortably above RPPL's real scale (~150 registrations/edition).
     */
    private const MAX_ROWS = 1000;

    /**
     * How many lines of each kind of note are handed back (the rest are
     * summarised), so a very messy sheet can't flood the session/page.
     */
    private const MAX_NOTES = 150;

    public function __construct(
        private readonly PlayerIdentityResolver $identity,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array{success: bool, dry_run: bool, created_registrations: int, created_players: int, skipped: int, errors: list<string>, notes: array{info: list<string>, skipped: list<string>, adjustments: list<string>}}
     */
    public function import(Edition $edition, UploadedFile $file, bool $dryRun = false): array
    {
        $parser = new RegistrationImportParser((string) $this->settings->get('system.display_timezone'));

        try {
            $sheet = $this->parseCsv($file, $parser);
        } catch (RuntimeException $e) {
            return $this->failure([$e->getMessage()]);
        }

        if ($sheet === null) {
            return $this->failure([__('The file must include a "name" column.')]);
        }

        if (count($sheet['rows']) === 0) {
            return $this->failure([__('No registration rows were found.')]);
        }

        if (count($sheet['rows']) > self::MAX_ROWS) {
            return $this->failure([__('The file contains more than :max rows. Split the file and import in smaller batches.', ['max' => self::MAX_ROWS])]);
        }

        $plan = $this->buildPlan($edition, $sheet, $parser);

        if (! empty($plan['errors'])) {
            return $this->failure($plan['errors']);
        }

        if ($dryRun) {
            $newPlayers = count(array_filter($plan['actions'], fn (array $action) => $action['existing_player_id'] === null));

            return $this->success(true, count($plan['actions']), $newPlayers, $plan);
        }

        return $this->writePlan($edition, $plan);
    }

    /**
     * Reads the CSV into rows keyed by the column each header fills (see
     * RegistrationImportParser::columnFor()). Returns null when no header
     * fills "name" (a structural problem, checked before any row is looked
     * at). Genuinely empty rows are dropped, but every row keeps its real
     * spreadsheet row number so messages point at the right line.
     *
     * @return array{rows: list<array{row: int, values: array<string, ?string>}>, ignored: list<string>, google: bool}|null
     */
    private function parseCsv(UploadedFile $file, RegistrationImportParser $parser): ?array
    {
        $records = $this->readRecords($file);
        $header = $records[0] ?? [];

        $columns = [];
        $ignored = [];
        $google = false;

        foreach ($header as $index => $title) {
            $title = trim((string) $title);

            if ($title === '') {
                continue;
            }

            // Only a Google Form's response sheet has a "Timestamp" column.
            if ($parser->normalizeHeader($title) === 'timestamp') {
                $google = true;
            }

            $column = $parser->columnFor($title);

            if ($column === null || in_array($column, $columns, true)) {
                $ignored[] = $title;

                continue;
            }

            $columns[$index] = $column;
        }

        if (! in_array('name', $columns, true)) {
            return null;
        }

        $rows = [];

        foreach (array_slice($records, 1, null, true) as $index => $data) {
            $rowNumber = $index + 1;

            if ($this->isBlankRow($data)) {
                continue;
            }

            $values = [];

            foreach ($columns as $index => $column) {
                $value = $data[$index] ?? null;
                $values[$column] = $value === null ? null : trim($value);
            }

            $rows[] = ['row' => $rowNumber, 'values' => $values];
        }

        return ['rows' => $rows, 'ignored' => $ignored, 'google' => $google];
    }

    /**
     * The file as rows of cell text, whether it is an Excel .xlsx (detected
     * by its zip signature, not its name) or a CSV. Row 1 is the header and
     * the list index + 1 is the spreadsheet row number.
     *
     * @return list<list<?string>>
     *
     * @throws RuntimeException when an .xlsx cannot be read
     */
    private function readRecords(UploadedFile $file): array
    {
        $content = (string) file_get_contents($file->getRealPath());

        if (XlsxReader::looksLikeXlsx($content)) {
            return XlsxReader::records($file->getRealPath());
        }

        // Strip a UTF-8 BOM that may prefix the first header cell.
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // A sheet saved from Excel as plain "CSV" is Windows-1252, not
        // UTF-8, and MySQL would refuse its bytes half-way through the
        // import. Valid UTF-8 (what Google Sheets exports) is untouched.
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $records = [];

        while (($data = fgetcsv($handle)) !== false) {
            $records[] = $data;
        }

        fclose($handle);

        return $records;
    }

    private function isBlankRow(array $data): bool
    {
        return array_filter($data, fn ($value) => trim((string) $value) !== '') === [];
    }

    /**
     * Pass 1: validates every row and resolves Player identity, without
     * writing anything. Rows that can't be imported but aren't an error
     * (already registered for this edition, or the same person appearing
     * twice in the file) are listed under "skipped". Problems with the
     * original columns (missing name, invalid payment status/fee/date, an
     * email/phone identity conflict, an inactive matched player) are fatal:
     * if any exist the whole import is rejected before pass 2 ever runs.
     *
     * Identity is matched with the same core logic the public guest
     * registration uses (PlayerIdentityResolver) — except for a Google Form
     * sheet. There the email is whichever Google account submitted the
     * form, which is routinely shared (one person filling the form for a
     * whole team), so it must never merge players or abort the import: the
     * mobile number is the identity, and the email is only saved on a new
     * player when no other player already has it.
     *
     * @param  array{rows: list<array{row: int, values: array<string, ?string>}>, ignored: list<string>, google: bool}  $sheet
     * @return array{errors: list<string>, actions: list<array<string, mixed>>, skipped: list<string>, adjustments: list<string>, info: list<string>}
     */
    private function buildPlan(Edition $edition, array $sheet, RegistrationImportParser $parser): array
    {
        $google = $sheet['google'];
        $errors = [];
        $actions = [];
        $skipped = [];
        $adjustments = [];
        $info = [];
        $seenIdentities = [];
        $claimedEmails = [];

        $timestamps = array_map(fn (array $row) => $row['values']['registered_at'] ?? null, $sheet['rows']);
        $dateOrder = $parser->dateOrder($timestamps);

        if ($dateOrder === 'conflict') {
            return [
                'errors' => ['The Timestamp column mixes day/month/year and month/day/year dates, so it cannot be read reliably. Make the dates consistent and try again.'],
                'actions' => [],
                'skipped' => [],
                'adjustments' => [],
                'info' => [],
            ];
        }

        if ($google) {
            $info[] = __('Google Form response sheet detected. Its Email Address is the account that submitted the form, so people are matched by mobile number, not by email.');
        }

        if ($parser->hasSlashDates($timestamps)) {
            $info[] = __('Timestamp dates were read as :order, in :zone time.', [
                'order' => $dateOrder === 'mdy' ? __('month/day/year (MM/DD/YYYY)') : __('day/month/year (DD/MM/YYYY)'),
                'zone' => $this->settings->get('system.display_timezone'),
            ]);
        }

        if ($sheet['ignored'] !== []) {
            $info[] = __('Columns not imported: :columns.', ['columns' => implode(', ', $sheet['ignored'])]);
        }

        foreach ($sheet['rows'] as $entry) {
            $rowNumber = $entry['row'];
            $values = $entry['values'];

            $name = $parser->name($values['name'] ?? null);

            if ($name === null) {
                $errors[] = __('Row :number: name is required.', ['number' => $rowNumber]);

                continue;
            }

            $label = __('Row :number (:name)', ['number' => $rowNumber, 'name' => $name]);

            // Same default the database column itself uses for a
            // manually-created registration with no status chosen.
            $statusRaw = $this->blank($values['payment_status'] ?? null);
            $paymentStatus = $statusRaw ?? 'pending';

            if (! in_array($paymentStatus, PlayerRegistration::PAYMENT_STATUSES, true)) {
                $errors[] = __('Row :number: invalid payment status ":status".', ['number' => $rowNumber, 'status' => $statusRaw]);

                continue;
            }

            $feeRaw = $this->blank($values['registration_fee'] ?? null);
            $registrationFee = null;

            if ($feeRaw !== null) {
                if (! is_numeric($feeRaw) || (float) $feeRaw < 0 || (float) $feeRaw > 99999999.99) {
                    $errors[] = __('Row :number: invalid registration fee.', ['number' => $rowNumber]);

                    continue;
                }

                $registrationFee = round((float) $feeRaw, 2);
            } elseif ($edition->registration_fee !== null) {
                // A form response carries no fee; like a public sign-up,
                // the registration owes the edition's fee.
                $registrationFee = (float) $edition->registration_fee;
            }

            $registeredAtRaw = $this->blank($values['registered_at'] ?? null);
            $registeredAt = null;

            if ($registeredAtRaw !== null) {
                $registeredAt = $parser->timestamp($registeredAtRaw, $dateOrder);

                if ($registeredAt === null) {
                    $errors[] = __('Row :number: invalid registered_at date.', ['number' => $rowNumber]);

                    continue;
                }
            }

            // The form's own answers: cleaned up, never fatal.
            $parsed = [
                'phone' => $parser->phone($values['phone'] ?? null),
                'email' => $parser->email($values['email'] ?? null),
                'age' => $parser->age($values['age'] ?? null),
                'primary_role' => $parser->role($values['primary_role'] ?? null),
                'batting_style' => $parser->battingHand($values['batting_style'] ?? null),
                'bowling_style' => $parser->bowlingArm($values['bowling_style'] ?? null),
                'village' => $parser->text($values['village'] ?? null, 100, 'village'),
                'tehsil' => $parser->text($values['tehsil'] ?? null, 100, 'tehsil'),
                'district' => $parser->text($values['district'] ?? null, 100, 'district'),
                'submitted_utr' => $parser->text($values['submitted_utr'] ?? null, 100, 'UTR'),
                'photo_url' => $parser->link($values['photo_url'] ?? null, __('photo link')),
                'payment_proof_url' => $parser->link($values['payment_proof_url'] ?? null, __('payment screenshot link')),
            ];

            $fields = [];
            $rowNotes = [];

            foreach ($parsed as $field => [$value, $note]) {
                $fields[$field] = $value;

                if ($note !== null) {
                    $rowNotes[] = $note;
                }
            }

            $phone = $fields['phone'];
            $email = $fields['email'];

            if ($google) {
                $existingPlayer = $phone !== null
                    ? Player::where('phone', $phone)->first()
                    : ($email !== null ? Player::where('email', $email)->first() : null);
            } else {
                $resolved = $this->identity->resolve($phone, $email);

                if ($resolved['conflict']) {
                    $errors[] = __('Row :number: email and phone belong to different players.', ['number' => $rowNumber]);

                    continue;
                }

                $existingPlayer = $resolved['player'];
            }

            if ($existingPlayer && ! $existingPlayer->is_active) {
                $errors[] = __('Row :number: matched player is inactive and cannot be registered.', ['number' => $rowNumber]);

                continue;
            }

            if ($existingPlayer && PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $existingPlayer->id)->exists()) {
                $skipped[] = __(':label: already registered for this edition — left as it is.', ['label' => $label]);

                continue;
            }

            // Identity used to catch the SAME person appearing twice in
            // this CSV. A row with neither a mobile number nor an email can
            // never be proven to be a duplicate of another such row, so
            // each gets its own key (never collapsed together).
            if ($existingPlayer) {
                $identityKey = 'player:'.$existingPlayer->id;
            } elseif ($google) {
                $identityKey = $phone !== null ? 'phone:'.$phone : ($email !== null ? 'email:'.$email : 'row:'.$rowNumber);
            } else {
                $identityKey = 'new:'.($email ?? $phone ?? 'row:'.$rowNumber);
            }

            if (isset($seenIdentities[$identityKey])) {
                $first = $seenIdentities[$identityKey];
                $skipped[] = __(':label: skipped — same person as row :row (:name), which is imported instead.', ['label' => $label, 'row' => $first['row'], 'name' => $first['name']]);

                continue;
            }

            $seenIdentities[$identityKey] = ['row' => $rowNumber, 'name' => $name];

            if ($existingPlayer) {
                $rowNotes[] = mb_strtolower($existingPlayer->name) === mb_strtolower($name)
                    ? __('matched the existing player by mobile number/email, so the registration is added to that player and their profile is left unchanged')
                    : __("matched the existing player ':player' by mobile number/email, so the registration is added to that player and their profile (including the name) is left unchanged", ['player' => $existingPlayer->name]);
            }

            // players.email is unique: a new player only gets the email if
            // nobody else has it, and the row is imported either way.
            $storedEmail = $email;

            if ($existingPlayer === null && $email !== null) {
                if (isset($claimedEmails[$email]) || Player::where('email', $email)->exists()) {
                    $storedEmail = null;
                    $rowNotes[] = __('email :email already belongs to another player — not saved on this player', ['email' => $email]);
                } else {
                    $claimedEmails[$email] = true;
                }
            }

            foreach ($rowNotes as $note) {
                $adjustments[] = __(':label: :note.', ['label' => $label, 'note' => $note]);
            }

            $actions[] = [
                'existing_player_id' => $existingPlayer?->id,
                'player' => [
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $storedEmail,
                    'primary_role' => $fields['primary_role'],
                    'batting_style' => $fields['batting_style'],
                    'bowling_style' => $fields['bowling_style'],
                ],
                'registration' => [
                    'payment_status' => $paymentStatus,
                    'registration_fee' => $registrationFee,
                    'registered_at' => $registeredAt,
                    'age' => $fields['age'],
                    'village' => $fields['village'],
                    'tehsil' => $fields['tehsil'],
                    'district' => $fields['district'],
                    'submitted_utr' => $fields['submitted_utr'],
                    'photo_url' => $fields['photo_url'],
                    'payment_proof_url' => $fields['payment_proof_url'],
                ],
            ];
        }

        return ['errors' => $errors, 'actions' => $actions, 'skipped' => $skipped, 'adjustments' => $adjustments, 'info' => $info];
    }

    /**
     * Pass 2: only reached when pass 1 found zero fatal problems.
     * Creates any needed Players and every planned PlayerRegistration
     * inside one transaction, so a rare unique-constraint race (two
     * imports resolving the same identity concurrently) rolls back the
     * entire file rather than leaving it half-imported.
     *
     * @param  array{actions: list<array<string, mixed>>, skipped: list<string>, adjustments: list<string>, info: list<string>}  $plan
     * @return array{success: bool, dry_run: bool, created_registrations: int, created_players: int, skipped: int, errors: list<string>, notes: array{info: list<string>, skipped: list<string>, adjustments: list<string>}}
     */
    private function writePlan(Edition $edition, array $plan): array
    {
        $createdPlayers = 0;
        $createdRegistrations = 0;
        $withLinks = [];

        try {
            DB::transaction(function () use ($edition, $plan, &$createdPlayers, &$createdRegistrations, &$withLinks) {
                foreach ($plan['actions'] as $action) {
                    $playerId = $action['existing_player_id'];

                    if ($playerId === null) {
                        $playerId = Player::create($action['player'])->id;
                        $createdPlayers++;
                    }

                    // registration_number is NOT NULL/UNIQUE and not
                    // fillable (never guest/CSV-controlled) — see
                    // PlayerRegistrationService::createRegistration()
                    // for the identical placeholder-then-assign pattern
                    // and why it's needed.
                    $registration = new PlayerRegistration(
                        ['edition_id' => $edition->id, 'player_id' => $playerId] + $action['registration']
                    );
                    $registration->registration_number = (string) Str::uuid();
                    $registration->save();
                    $registration->assignRegistrationNumber();

                    if ($registration->photo_url || $registration->payment_proof_url) {
                        $withLinks[] = $registration;
                    }

                    $createdRegistrations++;
                }
            });
        } catch (QueryException) {
            return $this->failure([__('The import could not be completed because of a data conflict. Please retry.')]);
        }

        // After the commit, off the request: copy each registration's
        // Drive photo / screenshot into private storage. A dispatch problem
        // never undoes the import; the registration keeps its links.
        foreach ($withLinks as $registration) {
            try {
                FetchRegistrationDriveFiles::dispatch($registration);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($withLinks !== []) {
            $plan['info'][] = count($withLinks).' registration(s) have a Google Drive photo or screenshot link. The files are being copied in the background (this needs a queue worker and files shared as "Anyone with the link"); any that cannot be copied keep their link, and can be retried from the registration page.';
        }

        return $this->success(false, $createdRegistrations, $createdPlayers, $plan);
    }

    /**
     * @param  array{skipped: list<string>, adjustments: list<string>, info: list<string>}  $plan
     * @return array{success: bool, dry_run: bool, created_registrations: int, created_players: int, skipped: int, errors: list<string>, notes: array{info: list<string>, skipped: list<string>, adjustments: list<string>}}
     */
    private function success(bool $dryRun, int $registrations, int $players, array $plan): array
    {
        return [
            'success' => true,
            'dry_run' => $dryRun,
            'created_registrations' => $registrations,
            'created_players' => $players,
            'skipped' => count($plan['skipped']),
            'errors' => [],
            'notes' => [
                'info' => $plan['info'],
                'skipped' => $this->limit($plan['skipped']),
                'adjustments' => $this->limit($plan['adjustments']),
            ],
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return array{success: bool, dry_run: bool, created_registrations: int, created_players: int, skipped: int, errors: list<string>, notes: array{info: list<string>, skipped: list<string>, adjustments: list<string>}}
     */
    private function failure(array $errors): array
    {
        return [
            'success' => false,
            'dry_run' => false,
            'created_registrations' => 0,
            'created_players' => 0,
            'skipped' => 0,
            'errors' => $errors,
            'notes' => ['info' => [], 'skipped' => [], 'adjustments' => []],
        ];
    }

    /**
     * @param  list<string>  $notes
     * @return list<string>
     */
    private function limit(array $notes): array
    {
        if (count($notes) <= self::MAX_NOTES) {
            return $notes;
        }

        $more = count($notes) - self::MAX_NOTES;

        return [...array_slice($notes, 0, self::MAX_NOTES), "…and {$more} more."];
    }

    private function blank(?string $value): ?string
    {
        return $value === '' || $value === null ? null : $value;
    }
}
