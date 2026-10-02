<?php

namespace Tests\Unit\Services\Registration;

use App\Services\Registration\RegistrationImportParser;
use App\Support\DriveLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure parsing only (no DB). Covers what actually matters for reading a
 * Google Form response sheet exactly as downloaded: which header fills
 * which column, and how forgivingly each typed answer is read.
 */
class RegistrationImportParserTest extends TestCase
{
    private function parser(): RegistrationImportParser
    {
        return new RegistrationImportParser('Asia/Kolkata');
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function headers(): array
    {
        return [
            'timestamp' => ['Timestamp', 'registered_at'],
            'email address' => ['Email Address', 'email'],
            'name' => ['Name', 'name'],
            'age' => ['Age', 'age'],
            'mobile with the emoji from the form' => ['Mobile 📱 Number', 'phone'],
            'mobile with an emoji variation selector' => ["Mobile 📱\u{FE0F} Number", 'phone'],
            'role' => ['Role', 'primary_role'],
            'bowling arm' => ['Bowling arm', 'bowling_style'],
            'batting hand as a plain header' => ['Batting hand', 'batting_style'],
            'hand' => ['Left hand/right hand', 'batting_style'],
            'gram' => ['Gram', 'village'],
            'tehsil in capitals' => ['TEHSIL', 'tehsil'],
            'district in capitals' => ['DISTRICT', 'district'],
            'photo' => ['Original Photos', 'photo_url'],
            'payment question' => ['Scan and pay 300', 'submitted_utr'],
            'payment question with another amount' => ['Scan and pay 500', 'submitted_utr'],
            'screenshot' => ['Upload payment screenshot', 'payment_proof_url'],
            'original format: fee' => ['registration_fee', 'registration_fee'],
            'original format: status' => ['Payment Status', 'payment_status'],
            'original format: registered at' => ['registered_at', 'registered_at'],
            'a column we do not import' => ['Verified by', null],
            'a plain "status" column is not the payment status' => ['Status', null],
            'blank header' => ['', null],
        ];
    }

    #[DataProvider('headers')]
    public function test_headers_are_matched_to_the_column_they_fill(string $header, ?string $column): void
    {
        $this->assertSame($column, $this->parser()->columnFor($header));
    }

    /**
     * @return array<string, array{string, ?string, bool}>
     */
    public static function phones(): array
    {
        return [
            'plain' => ['9876543210', '9876543210', false],
            'spaces' => ['98765 43210', '9876543210', false],
            'country code with dashes' => ['+91 98765-43210', '9876543210', false],
            'country code without a plus' => ['919876543210', '9876543210', false],
            'leading zero' => ['09876543210', '9876543210', false],
            'double zero country code' => ['0091 98765 43210', '9876543210', false],
            'two numbers: the first valid one' => ['9876543210, 9123456780', '9876543210', false],
            'two numbers separated by a space' => ['9876543210 9123456780', '9876543210', false],
            'not a valid mobile: kept as typed' => ['12345', '12345', true],
            'no digits at all' => ['NA', null, true],
            'too long to be a phone number' => ['1234567890123456', null, true],
            'blank' => ['', null, false],
        ];
    }

    #[DataProvider('phones')]
    public function test_mobile_numbers_are_cleaned_up_without_ever_failing(string $raw, ?string $phone, bool $hasNote): void
    {
        [$value, $note] = $this->parser()->phone($raw);

        $this->assertSame($phone, $value);
        $this->assertSame($hasNote, $note !== null);
    }

    /**
     * @return array<string, array{string, ?int}>
     */
    public static function ages(): array
    {
        return [
            'number' => ['22', 22],
            'number with words' => ['22 years', 22],
            'padded' => [' 17 ', 17],
            'a birth year is not an age' => ['2004', null],
            'words' => ['twenty', null],
            'a range' => ['22-25', null],
            'too young' => ['3', null],
            'too old' => ['100', null],
            'blank' => ['', null],
        ];
    }

    #[DataProvider('ages')]
    public function test_age_is_read_only_when_it_is_a_plausible_number(string $raw, ?int $age): void
    {
        [$value, $note] = $this->parser()->age($raw);

        $this->assertSame($age, $value);
        // Anything typed but unusable is explained; a blank cell is not.
        $this->assertSame($age === null && trim($raw) !== '', $note !== null);
    }

    public function test_role_and_hand_answers_map_to_the_stored_values(): void
    {
        $parser = $this->parser();

        foreach (['Batter' => 'batter', 'All rounder' => 'all_rounder', 'all-rounder' => 'all_rounder', 'Bowler' => 'bowler', 'Wicket Keeper' => 'wicket_keeper'] as $answer => $role) {
            $this->assertSame([$role, null], $parser->role($answer), $answer);
        }

        foreach (['Right hand' => 'right_hand', 'Left hand' => 'left_hand', 'left' => 'left_hand', 'R' => 'right_hand'] as $answer => $hand) {
            $this->assertSame([$hand, null], $parser->battingHand($answer), $answer);
        }

        foreach (['Right arm' => 'right_arm', 'left' => 'left_arm', "Doesn't bowl" => 'none', 'None' => 'none'] as $answer => $arm) {
            $this->assertSame([$arm, null], $parser->bowlingArm($answer), $answer);
        }
        $this->assertNull($parser->bowlingArm('Spin')[0]);
        $this->assertNotNull($parser->bowlingArm('Spin')[1]);

        // Unknown answers are dropped with an explanation, never invented.
        $this->assertNull($parser->role('Captain')[0]);
        $this->assertNotNull($parser->role('Captain')[1]);
        $this->assertNull($parser->battingHand('Both')[0]);
        $this->assertNotNull($parser->battingHand('Both')[1]);
        $this->assertSame([null, null], $parser->role(''));
    }

    public function test_a_timestamp_with_a_time_is_read_in_the_display_timezone_and_returned_in_utc(): void
    {
        $parser = $this->parser();

        // 14:35 in India is 09:05 UTC.
        $this->assertSame('2026-10-02 09:05:12', $parser->timestamp('2/10/2026 14:35:12', 'dmy')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-10 09:05:12', $parser->timestamp('2/10/2026 14:35:12', 'mdy')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-02 09:05:12', $parser->timestamp('2/10/2026 2:35:12 PM', 'dmy')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-01 04:30:00', $parser->timestamp('2026/01/01 10:00:00', 'dmy')->format('Y-m-d H:i:s'));
    }

    public function test_a_date_without_a_time_stays_that_calendar_date_at_midnight_utc(): void
    {
        // As the original CSV import always stored it, so the admin lists
        // (which show UTC) still show the same day.
        $this->assertSame('2026-01-05 00:00:00', $this->parser()->timestamp('2026-01-05', 'dmy')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-13 00:00:00', $this->parser()->timestamp('13/01/2026', 'dmy')->format('Y-m-d H:i:s'));
    }

    public function test_timestamps_that_cannot_be_read_are_rejected_rather_than_guessed(): void
    {
        $parser = $this->parser();

        $this->assertNull($parser->timestamp('31/02/2026 10:00:00', 'dmy'), 'impossible date');
        $this->assertNull($parser->timestamp('13/01/2026', 'mdy'), 'month 13 in the month-first order');
        $this->assertNull($parser->timestamp('last tuesday-ish', 'dmy'));
        $this->assertNotNull($parser->timestamp('5 Jan 2026 10:00', 'dmy'), 'free text a person might type is still read');
    }

    public function test_the_date_order_comes_from_the_data_and_defaults_to_day_first(): void
    {
        $parser = $this->parser();

        $this->assertSame('dmy', $parser->dateOrder(['02/10/2026 10:00:00']), 'ambiguous: the India default');
        $this->assertSame('dmy', $parser->dateOrder(['02/10/2026', '13/01/2026']), 'a 13 in the first part proves day-first');
        $this->assertSame('mdy', $parser->dateOrder(['02/10/2026', '01/13/2026']), 'a 13 in the second part proves month-first');
        $this->assertSame('conflict', $parser->dateOrder(['13/01/2026', '01/13/2026']));
        $this->assertSame('dmy', $parser->dateOrder(['2026-01-05', null]));
        $this->assertFalse($parser->hasSlashDates(['2026-01-05']));
        $this->assertTrue($parser->hasSlashDates(['2/10/2026 14:35:12']));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function links(): array
    {
        return [
            'google form file upload' => ['https://drive.google.com/open?id=1AbC_dEf', true],
            'google docs host' => ['https://docs.google.com/uc?id=1', true],
            'google user content host' => ['https://lh3.googleusercontent.com/abc', true],
            'plain http' => ['http://drive.google.com/open?id=1', false],
            'javascript scheme' => ['javascript:alert(1)', false],
            'another site' => ['https://example.com/photo.png', false],
            'look-alike domain' => ['https://drive.google.com.evil.example/x', false],
            'google as a path on another site' => ['https://evil.example/drive.google.com', false],
            'embedded credentials' => ['https://user:pass@drive.google.com/x', false],
            'not a url' => ['drive.google.com/open?id=1', false],
        ];
    }

    #[DataProvider('links')]
    public function test_only_https_links_on_google_hosts_are_accepted(string $url, bool $valid): void
    {
        $this->assertSame($valid, DriveLink::isValid($url));
        $this->assertSame($valid ? $url : null, $this->parser()->link($url, 'photo link')[0]);
    }

    public function test_the_first_valid_link_in_a_cell_is_kept(): void
    {
        $cell = 'https://example.com/x, https://drive.google.com/open?id=1, https://drive.google.com/open?id=2';

        $this->assertSame('https://drive.google.com/open?id=1', DriveLink::first($cell));
        $this->assertSame([null, null], $this->parser()->link('', 'photo link'));
    }

    public function test_free_text_is_tidied_and_shortened_not_rejected(): void
    {
        $parser = $this->parser();

        $this->assertSame(['Sendriya Kalan', null], $parser->text("  Sendriya \n  Kalan ", 100, 'village'));
        $this->assertSame([null, null], $parser->text('   ', 100, 'village'));

        [$value, $note] = $parser->text(str_repeat('a', 150), 100, 'village');
        $this->assertSame(100, mb_strlen($value));
        $this->assertStringContainsString('shortened', $note);
    }

    public function test_email_and_name_are_normalised_and_junk_is_dropped(): void
    {
        $parser = $this->parser();

        $this->assertSame(['foo@example.com', null], $parser->email('  Foo@Example.COM '));
        $this->assertSame([null, null], $parser->email(''));
        $this->assertNull($parser->email('not-an-email')[0]);
        $this->assertNotNull($parser->email('not-an-email')[1]);

        $this->assertSame('Amit Kumar Verma', $parser->name("  Amit   Kumar\tVerma "));
        $this->assertNull($parser->name('   '));
    }
}
