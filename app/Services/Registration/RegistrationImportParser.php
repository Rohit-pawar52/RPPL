<?php

namespace App\Services\Registration;

use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Support\DriveLink;
use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Turns the raw text cells of an imported registration CSV into the values
 * the app stores. Built for a Google Form response sheet downloaded exactly
 * as it is — answers typed by players on a phone, with no validation behind
 * them — so everything here is forgiving: each value method returns
 * [cleaned value, note], where the note says (in plain words, for the
 * admin) what had to be dropped or guessed. Nothing here throws, and a
 * messy free-text answer never turns into a failed import.
 *
 * Only the timestamp is strict (see timestamp()): a date that cannot be
 * read is reported as an error rather than silently stored wrong.
 */
class RegistrationImportParser
{
    /**
     * Normalised header text => the column it fills. The first six are the
     * original CSV format; the rest cover the Google Form's own wording.
     */
    private const COLUMNS = [
        'name' => 'name',
        'full name' => 'name',
        'player name' => 'name',
        'phone' => 'phone',
        'phone number' => 'phone',
        'mobile' => 'phone',
        'mobile number' => 'phone',
        'mobile no' => 'phone',
        'contact number' => 'phone',
        'email' => 'email',
        'email address' => 'email',
        'e mail' => 'email',
        'registration fee' => 'registration_fee',
        'payment status' => 'payment_status',
        'registered at' => 'registered_at',
        'timestamp' => 'registered_at',
        'age' => 'age',
        'role' => 'primary_role',
        'player role' => 'primary_role',
        'player type' => 'primary_role',
        'gram' => 'village',
        'village' => 'village',
        'tehsil' => 'tehsil',
        'tahsil' => 'tehsil',
        'district' => 'district',
        'utr' => 'submitted_utr',
        'utr number' => 'submitted_utr',
        'transaction id' => 'submitted_utr',
        'original photo' => 'photo_url',
        'original photos' => 'photo_url',
        'photo' => 'photo_url',
        'photos' => 'photo_url',
        'bowling arm' => 'bowling_style',
        'bowling hand' => 'bowling_style',
        'bowling style' => 'bowling_style',
        'payment screenshot' => 'payment_proof_url',
        'upload payment screenshot' => 'payment_proof_url',
        'payment proof' => 'payment_proof_url',
    ];

    /**
     * For headers whose exact wording varies from form to form: the
     * amount in "Scan and pay 300" is part of the question text, a form
     * may say "Upload your photo", and so on. Order matters — the payment
     * screenshot is checked before the plain photo.
     */
    private const COLUMN_PATTERNS = [
        '/^scan and pay\b/' => 'submitted_utr',
        '/\b(screenshot|payment proof)\b|\bpayment\b.*\b(photo|image)\b/' => 'payment_proof_url',
        '/\b(photo|photos|picture)\b/' => 'photo_url',
        '/\b(mobile|phone|whatsapp)\b/' => 'phone',
        '/\b(utr|transaction)\b/' => 'submitted_utr',
        '/\bbowling\b/' => 'bowling_style',
        '/\bhand\b/' => 'batting_style',
    ];

    /**
     * Role answers, with spaces and punctuation stripped, to a
     * Player::PRIMARY_ROLES value.
     */
    private const ROLES = [
        'batter' => 'batter',
        'batsman' => 'batter',
        'batsmen' => 'batter',
        'batting' => 'batter',
        'bowler' => 'bowler',
        'bowling' => 'bowler',
        'allrounder' => 'all_rounder',
        'allround' => 'all_rounder',
        'wicketkeeper' => 'wicket_keeper',
        'wicketkeeping' => 'wicket_keeper',
        'keeper' => 'wicket_keeper',
        'wk' => 'wicket_keeper',
    ];

    public function __construct(private readonly string $timezone) {}

    /**
     * Lower-cased, with every run of punctuation, symbols and emoji turned
     * into one space — so "Mobile 📱 Number", "Left hand/right hand" and
     * "registration_fee" all reduce to plain words.
     */
    public function normalizeHeader(string $header): string
    {
        $header = str_replace(["\u{FE0F}", "\u{200D}", "\u{200B}", "\u{FEFF}"], '', $header);

        return trim((string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', mb_strtolower($header)));
    }

    /**
     * The column a CSV header fills, or null when it isn't one we import.
     */
    public function columnFor(string $header): ?string
    {
        $key = $this->normalizeHeader($header);

        if ($key === '') {
            return null;
        }

        if (isset(self::COLUMNS[$key])) {
            return self::COLUMNS[$key];
        }

        foreach (self::COLUMN_PATTERNS as $pattern => $column) {
            if (preg_match($pattern, $key)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * A Google Sheet exports 02/10/2026 for 2 October in India and for 10
     * February in the US, with nothing in the file saying which. The data
     * can settle it: a first part above 12 can only be a day (day-first),
     * a second part above 12 can only be a day too (month-first). With no
     * such evidence the day-first order is used — the one a sheet created
     * in India has. 'conflict' means the file proves both, i.e. it mixes
     * the two orders.
     *
     * @param  list<?string>  $values
     * @return 'dmy'|'mdy'|'conflict'
     */
    public function dateOrder(array $values): string
    {
        $dayFirst = false;
        $monthFirst = false;

        foreach ($values as $value) {
            if (! preg_match('#^\s*(\d{1,2})/(\d{1,2})/\d{4}#', (string) $value, $parts)) {
                continue;
            }

            $dayFirst = $dayFirst || (int) $parts[1] > 12;
            $monthFirst = $monthFirst || (int) $parts[2] > 12;
        }

        if ($dayFirst && $monthFirst) {
            return 'conflict';
        }

        return $monthFirst ? 'mdy' : 'dmy';
    }

    /**
     * @param  list<?string>  $values
     */
    public function hasSlashDates(array $values): bool
    {
        foreach ($values as $value) {
            if (preg_match('#^\s*\d{1,2}/\d{1,2}/\d{4}#', (string) $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A timestamp with a time of day (the Google Form's "Timestamp") is
     * read in the display timezone and returned in UTC, like every other
     * stored datetime. A date on its own is kept as that date at 00:00 UTC,
     * as the original CSV import always did, so it still shows the same
     * calendar date in the admin lists. Returns null for anything that
     * can't be read, including impossible dates such as 31/02/2026.
     */
    public function timestamp(string $raw, string $dateOrder): ?Carbon
    {
        $raw = trim((string) preg_replace('/\s+/', ' ', $raw));
        $slash = $dateOrder === 'mdy' ? 'm/d/Y' : 'd/m/Y';

        $formats = [
            'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y/m/d H:i:s', 'Y/m/d H:i',
            "{$slash} H:i:s", "{$slash} H:i", "{$slash} g:i:s A", "{$slash} g:i A",
            'Y-m-d', 'Y/m/d', $slash,
        ];

        foreach ($formats as $format) {
            $parsed = $this->parseExact($format, $raw);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        // Anything else a person might type ("5 Jan 2026 10:00") is read as
        // before — but never a numeric a/b/Y date, which would be guessed
        // in the American order here, and only when it names a full date
        // (not "tomorrow" or "last tuesday-ish").
        if (preg_match('#^\d{1,2}/\d{1,2}/\d{2,4}#', $raw)) {
            return null;
        }

        $parts = date_parse($raw);

        if ($parts['error_count'] > 0 || $parts['warning_count'] > 0
            || $parts['year'] === false || $parts['month'] === false || $parts['day'] === false) {
            return null;
        }

        try {
            return Carbon::parse($raw)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    public function name(?string $raw): ?string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));

        return $name === '' ? null : mb_substr($name, 0, 255);
    }

    /**
     * Cleans the usual ways a mobile number gets typed (+91, 0091, a
     * leading 0, spaces, dashes, two numbers in one cell) down to the
     * 10-digit form the public form stores. A number that still isn't a
     * valid Indian mobile is kept as typed so the admin can fix it, unless
     * it is too long for the column.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function phone(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [null, null];
        }

        $tokens = preg_split('/[,;\/|\n&]+|\s+(?:or|and)\s+/i', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            $digits = $this->phoneDigits($token);

            if (preg_match('/^[6-9]\d{9}$/', $digits)) {
                return [$digits, null];
            }
        }

        // "9876543210 9123456780", "Ph: 9876543210" — a standalone mobile
        // number anywhere in the text.
        if (preg_match('/(?<!\d)[6-9]\d{9}(?!\d)/', $raw, $found)) {
            return [$found[0], null];
        }

        $digits = $this->phoneDigits($tokens[0] ?? $raw);
        $shown = $this->shorten($raw);

        if ($digits === '') {
            return [null, "mobile number '{$shown}' has no digits — left empty"];
        }

        if (strlen($digits) > 15) {
            return [null, "mobile number '{$shown}' is too long to be a phone number — left empty"];
        }

        return [$digits, "mobile number '{$shown}' is not a valid 10-digit Indian mobile — saved as typed"];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function email(?string $raw): array
    {
        $email = Player::normalizeEmail($raw);

        if ($email === null) {
            return [null, null];
        }

        if (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [null, "email '{$this->shorten($email)}' is not a valid email address — not saved"];
        }

        return [$email, null];
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    public function age(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [null, null];
        }

        // One number, with or without words around it ("22", "22 years");
        // "2004" (a birth year) and "22-25" fall through to the note.
        if (preg_match('/^\D*(\d{1,3})(?:[.,]\d+)?\D*$/u', $raw, $parts)) {
            $age = (int) $parts[1];

            if ($age >= PlayerRegistration::AGE_MIN && $age <= PlayerRegistration::AGE_MAX) {
                return [$age, null];
            }
        }

        return [null, sprintf(
            "age '%s' could not be read as an age (%d-%d) — left empty",
            $this->shorten($raw),
            PlayerRegistration::AGE_MIN,
            PlayerRegistration::AGE_MAX,
        )];
    }

    /**
     * @return array{0: ?string, 1: ?string} a Player::PRIMARY_ROLES value
     */
    public function role(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [null, null];
        }

        $key = (string) preg_replace('/[^a-z]/', '', mb_strtolower($raw));

        if (isset(self::ROLES[$key])) {
            return [self::ROLES[$key], null];
        }

        return [null, "role '{$this->shorten($raw)}' is not Batter / Bowler / All rounder / Wicket keeper — left empty"];
    }

    /**
     * The form's single "Left hand/right hand" question is kept as the
     * player's batting hand (it can't tell batting from bowling).
     *
     * @return array{0: ?string, 1: ?string} a Player::BATTING_STYLES value
     */
    public function battingHand(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [null, null];
        }

        $key = (string) preg_replace('/[^a-z]/', '', mb_strtolower($raw));

        if ($key === 'r' || str_starts_with($key, 'right')) {
            return ['right_hand', null];
        }

        if ($key === 'l' || str_starts_with($key, 'left')) {
            return ['left_hand', null];
        }

        return [null, "hand '{$this->shorten($raw)}' is not Right hand / Left hand — left empty"];
    }

    /**
     * The bowling arm: right/left arm, or "none" for someone who doesn't
     * bowl.
     *
     * @return array{0: ?string, 1: ?string} a Player::BOWLING_STYLES value
     */
    public function bowlingArm(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [null, null];
        }

        $key = (string) preg_replace('/[^a-z]/', '', mb_strtolower($raw));

        if ($key === 'r' || str_starts_with($key, 'right')) {
            return ['right_arm', null];
        }

        if ($key === 'l' || str_starts_with($key, 'left')) {
            return ['left_arm', null];
        }

        if (in_array($key, ['none', 'no', 'na', 'doesntbowl', 'dontbowl', 'notbowling', 'nonbowler'], true)) {
            return ['none', null];
        }

        return [null, "bowling arm '{$this->shorten($raw)}' is not Right arm / Left arm / None — left empty"];
    }

    /**
     * Free text (village, tehsil, district, UTR): spaces tidied, cut to the
     * column length rather than failing the row.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function text(?string $raw, int $max, string $label): array
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));

        if ($value === '') {
            return [null, null];
        }

        if (mb_strlen($value) > $max) {
            return [mb_substr($value, 0, $max), "{$label} was longer than {$max} characters — shortened"];
        }

        return [$value, null];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function link(?string $raw, string $label): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [null, null];
        }

        $link = DriveLink::first($raw);

        if ($link !== null) {
            return [$link, null];
        }

        return [null, "{$label} '{$this->shorten($raw)}' is not a Google Drive link — not saved"];
    }

    private function parseExact(string $format, string $value): ?Carbon
    {
        $hasTime = (bool) preg_match('/[HGgh]/', $format);
        $zone = new DateTimeZone($hasTime ? $this->timezone : 'UTC');

        // The leading "!" zeroes every field the format doesn't mention —
        // without it PHP fills a missing time of day with the current time.
        $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value, $zone);
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return Carbon::instance($parsed)->utc();
    }

    private function phoneDigits(string $token): string
    {
        $digits = (string) Player::normalizePhone($token);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);

            if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
                $digits = substr($digits, 2);
            }
        } elseif (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    private function shorten(string $value): string
    {
        return mb_strimwidth($value, 0, 40, '…');
    }
}
