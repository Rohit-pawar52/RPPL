<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The admin CSV import, fed the Google Form response sheet exactly as
 * Google Sheets downloads it. The original column format has its own
 * tests in PlayerRegistrationImportTest and must keep passing unchanged.
 */
class PlayerRegistrationGoogleFormImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The sheet's columns, in the order and with the exact wording (emoji
     * included) of the Season 3 registration form.
     */
    private const HEADER = [
        'Timestamp', 'Email Address', 'Name', 'Age', 'Mobile 📱 Number', 'Role', 'Left hand/right hand',
        'Gram', 'TEHSIL', 'DISTRICT', 'Original Photos', 'Scan and pay 300', 'Upload payment screenshot',
    ];

    private Role $adminRole;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function edition(): Edition
    {
        return Edition::factory()->create(['status' => 'active', 'registration_fee' => 300]);
    }

    /**
     * One response with every question answered; $answers overrides by header.
     *
     * @param  array<string, string>  $answers
     * @return array<string, string>
     */
    private function response(array $answers = []): array
    {
        $n = ++$this->sequence;

        return array_merge([
            'Timestamp' => '02/10/2026 14:35:12',
            'Email Address' => "player{$n}@example.com",
            'Name' => "Player {$n}",
            'Age' => '22',
            'Mobile 📱 Number' => '98765'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'Role' => 'Batter',
            'Left hand/right hand' => 'Right hand',
            'Gram' => 'Sendriya',
            'TEHSIL' => 'Multai',
            'DISTRICT' => 'Betul',
            'Original Photos' => "https://drive.google.com/open?id=PHOTO{$n}",
            'Scan and pay 300' => "40291234{$n}567",
            'Upload payment screenshot' => "https://drive.google.com/open?id=PROOF{$n}",
        ], $answers);
    }

    /**
     * The sheet as a CSV string (UTF-8). A response may carry extra
     * columns the form doesn't have; they are appended to the header.
     *
     * @param  array<string, string>  ...$responses
     */
    private function sheet(array ...$responses): string
    {
        $header = self::HEADER;

        foreach ($responses as $response) {
            $header = array_values(array_unique([...$header, ...array_keys($response)]));
        }

        $handle = fopen('php://memory', 'r+');
        fputcsv($handle, $header);

        foreach ($responses as $response) {
            fputcsv($handle, array_map(fn (string $column) => $response[$column] ?? '', $header));
        }

        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function import(Edition $edition, string $csv, array $extra = []): TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.player-registrations.import.store'), [
            'edition_id' => $edition->id,
            'csv_file' => UploadedFile::fake()->createWithContent('responses.csv', $csv),
            ...$extra,
        ]);
    }

    public function test_a_google_form_sheet_imports_every_answer_into_the_registration(): void
    {
        $edition = $this->edition();

        $response = $this->import($edition, $this->sheet($this->response([
            'Name' => 'Amit Verma',
            'Email Address' => 'amit@example.com',
            'Mobile 📱 Number' => '+91 98765 43210',
            'Age' => '23',
            'Role' => 'All rounder',
            'Left hand/right hand' => 'Left hand',
            'Gram' => 'Sendriya',
            'TEHSIL' => 'Multai',
            'DISTRICT' => 'Betul',
            'Scan and pay 300' => '402912345678',
            'Original Photos' => 'https://drive.google.com/open?id=PHOTOID',
            'Upload payment screenshot' => 'https://drive.google.com/open?id=PROOFID',
            'Verified by' => 'Rohit',
        ])));

        $response->assertRedirect(route('admin.player-registrations.index', ['edition_id' => $edition->id]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '1 registration created') && str_contains($message, '1 new player created'))
            ->assertSessionHas('import_notes', function (array $notes) {
                $info = implode("\n", $notes['info']);
                $this->assertStringContainsString('Google Form response sheet detected', $info);
                $this->assertStringContainsString('day/month/year', $info);
                $this->assertStringContainsString('Columns not imported: Verified by', $info);

                return true;
            });

        $player = Player::firstWhere('phone', '9876543210');
        $this->assertNotNull($player);
        $this->assertSame('Amit Verma', $player->name);
        $this->assertSame('amit@example.com', $player->email);
        $this->assertSame('all_rounder', $player->primary_role);
        $this->assertSame('left_hand', $player->batting_style);
        $this->assertNull($player->date_of_birth);
        $this->assertTrue($player->is_active);

        $registration = PlayerRegistration::where('player_id', $player->id)->sole();
        $this->assertSame($edition->id, $registration->edition_id);
        $this->assertMatchesRegularExpression('/^RPPL-'.$edition->year.'-\d{6}$/', $registration->registration_number);
        $this->assertSame('pending', $registration->payment_status);
        $this->assertSame('300.00', $registration->registration_fee);
        $this->assertSame(23, $registration->age);
        $this->assertSame('Sendriya', $registration->village);
        $this->assertSame('Multai', $registration->tehsil);
        $this->assertSame('Betul', $registration->district);
        $this->assertSame('402912345678', $registration->submitted_utr);
        $this->assertSame('https://drive.google.com/open?id=PHOTOID', $registration->photo_url);
        $this->assertSame('https://drive.google.com/open?id=PROOFID', $registration->payment_proof_url);
        // 14:35 in India (the display timezone) is 09:05 UTC.
        $this->assertSame('2026-10-02 09:05:12', $registration->registered_at->format('Y-m-d H:i:s'));
        // The files themselves cannot come through a CSV.
        $this->assertNull($registration->payment_proof_path);
        $this->assertNull($registration->aadhaar_document_path);
        $this->assertNull($registration->payment_reference);
    }

    public function test_check_only_reports_what_would_happen_without_writing_anything(): void
    {
        $edition = $this->edition();
        $existing = Player::factory()->create(['phone' => '9000000001']);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $existing->id]);

        // Two new people, and one who is already registered.
        $csv = $this->sheet($this->response(), $this->response(), $this->response(['Mobile 📱 Number' => '9000000001']));

        $this->import($edition, $csv, ['dry_run' => '1'])
            ->assertRedirect(route('admin.player-registrations.import'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('import_check', fn (array $check) => $check['dry_run'] === true
                && $check['created_registrations'] === 2
                && $check['created_players'] === 2
                && $check['skipped'] === 1);

        $this->assertSame(1, Player::count());
        $this->assertSame(1, PlayerRegistration::count());

        // The same file without "check only" really imports it.
        $this->import($edition, $csv)->assertSessionHas('success');
        $this->assertSame(3, Player::count());
        $this->assertSame(3, PlayerRegistration::count());
    }

    public function test_the_check_only_result_and_the_import_notes_are_shown_on_the_pages(): void
    {
        $edition = $this->edition();
        $csv = $this->sheet(
            $this->response(['Name' => 'Shown Player', 'Age' => 'twenty']),
            $this->response(['Name' => 'Repeated Player', 'Mobile 📱 Number' => '9876543210']),
            $this->response(['Name' => 'Repeated Again', 'Mobile 📱 Number' => '9876543210']),
        );

        $this->followingRedirects()
            ->import($edition, $csv, ['dry_run' => '1'])
            ->assertOk()
            ->assertSee('Check only &mdash; nothing was imported.', false)
            ->assertSee('would create')
            ->assertSee("age 'twenty' could not be read")
            ->assertSee('Rows not imported');

        $this->assertSame(0, PlayerRegistration::count());

        $this->followingRedirects()
            ->import($edition, $csv)
            ->assertOk()
            ->assertSee('Import notes &mdash; please review', false)
            ->assertSee('Row 4 (Repeated Again): skipped')
            ->assertSee('Shown Player');
    }

    public function test_messy_answers_are_cleaned_up_and_listed_instead_of_rejecting_the_file(): void
    {
        $edition = $this->edition();

        $response = $this->import($edition, $this->sheet(
            $this->response([
                'Name' => 'Messy One',
                'Mobile 📱 Number' => '+91 98765-43210',
                'Age' => '22 years',
                'Role' => 'all-rounder',
                'Left hand/right hand' => 'left',
                'Gram' => '  Sendriya   Kalan ',
                'Original Photos' => 'javascript:alert(1)',
                'Scan and pay 300' => str_repeat('9', 130),
                'Upload payment screenshot' => 'https://drive.google.com/open?id=OK',
            ]),
            $this->response([
                'Name' => 'Messy Two',
                'Email Address' => 'not-an-email',
                'Mobile 📱 Number' => '12345',
                'Age' => 'twenty',
                'Role' => 'Captain',
                'Left hand/right hand' => 'Both',
                'Upload payment screenshot' => 'https://evil.example.com/x.png',
            ]),
        ));

        $response->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertSessionHas('import_notes', function (array $notes) {
                $lines = $notes['adjustments'];
                $joined = implode("\n", $lines);

                // Row 1 is the header, so the responses are rows 2 and 3.
                $this->assertStringContainsString('Row 2 (Messy One): photo link', $joined);
                $this->assertStringContainsString('UTR was longer than 100 characters', $joined);
                $this->assertStringContainsString("Row 3 (Messy Two): email 'not-an-email' is not a valid email address", $joined);
                $this->assertStringContainsString("mobile number '12345' is not a valid 10-digit Indian mobile", $joined);
                $this->assertStringContainsString("age 'twenty' could not be read", $joined);
                $this->assertStringContainsString("role 'Captain'", $joined);
                $this->assertStringContainsString("hand 'Both'", $joined);
                $this->assertStringContainsString('payment screenshot link', $joined);
                // Nothing was wrong with the first row's phone or age.
                $this->assertStringNotContainsString('Row 2 (Messy One): mobile', $joined);
                $this->assertStringNotContainsString('Row 2 (Messy One): age', $joined);

                return true;
            });

        $one = Player::firstWhere('name', 'Messy One');
        $this->assertSame('9876543210', $one->phone);
        $this->assertSame('all_rounder', $one->primary_role);
        $this->assertSame('left_hand', $one->batting_style);

        $oneRegistration = PlayerRegistration::where('player_id', $one->id)->sole();
        $this->assertSame(22, $oneRegistration->age);
        $this->assertSame('Sendriya Kalan', $oneRegistration->village);
        $this->assertNull($oneRegistration->photo_url, 'a javascript: link is never stored');
        $this->assertSame('https://drive.google.com/open?id=OK', $oneRegistration->payment_proof_url);
        $this->assertSame(100, mb_strlen($oneRegistration->submitted_utr));

        // Every unusable answer was left empty, but the player still registered.
        $two = Player::firstWhere('name', 'Messy Two');
        $this->assertSame('12345', $two->phone);
        $this->assertNull($two->email);
        $this->assertNull($two->primary_role);
        $this->assertNull($two->batting_style);

        $twoRegistration = PlayerRegistration::where('player_id', $two->id)->sole();
        $this->assertNull($twoRegistration->age);
        $this->assertNull($twoRegistration->payment_proof_url);
    }

    public function test_a_shared_google_account_email_never_merges_players_and_reimporting_only_skips(): void
    {
        $edition = $this->edition();

        // One person fills the form for two team-mates from their own account.
        $csv = $this->sheet(
            $this->response(['Name' => 'First Mate', 'Email Address' => 'team@example.com']),
            $this->response(['Name' => 'Second Mate', 'Email Address' => 'team@example.com']),
        );

        $this->import($edition, $csv)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '2 registrations created'))
            ->assertSessionHas('import_notes', fn (array $notes) => str_contains(implode("\n", $notes['adjustments']), 'Row 3 (Second Mate): email team@example.com already belongs to another player'));

        $this->assertSame(2, Player::count());
        $this->assertSame(2, PlayerRegistration::count());
        $this->assertSame('First Mate', Player::firstWhere('email', 'team@example.com')->name);
        $this->assertNull(Player::firstWhere('name', 'Second Mate')->email);

        // Importing the same sheet again (it keeps growing) adds nothing and
        // is not an email conflict.
        $this->import($edition, $csv)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '0 registrations created') && str_contains($message, '2 skipped'));

        $this->assertSame(2, Player::count());
        $this->assertSame(2, PlayerRegistration::count());
    }

    public function test_a_google_row_whose_email_belongs_to_a_different_existing_player_is_still_imported(): void
    {
        $edition = $this->edition();
        $organiser = Player::factory()->create(['email' => 'organiser@example.com', 'phone' => '9000000001']);

        $this->import($edition, $this->sheet($this->response([
            'Name' => 'Teammate',
            'Email Address' => 'organiser@example.com',
            'Mobile 📱 Number' => '9876543210',
        ])))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $teammate = Player::firstWhere('phone', '9876543210');
        $this->assertNotNull($teammate);
        $this->assertNull($teammate->email);
        $this->assertSame(2, Player::count());
        $this->assertSame(0, PlayerRegistration::where('player_id', $organiser->id)->count());
        $this->assertSame(1, PlayerRegistration::where('player_id', $teammate->id)->count());
    }

    public function test_a_google_row_matching_an_existing_player_by_mobile_registers_that_player_and_leaves_the_profile_alone(): void
    {
        $edition = $this->edition();
        $player = Player::factory()->create([
            'name' => 'Old Name',
            'phone' => '9876543210',
            'primary_role' => 'bowler',
            'batting_style' => 'left_hand',
        ]);

        $this->import($edition, $this->sheet($this->response([
            'Name' => 'New Name',
            'Mobile 📱 Number' => '98765 43210',
            'Role' => 'Batter',
            'Left hand/right hand' => 'Right hand',
        ])))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('import_notes', fn (array $notes) => str_contains(implode("\n", $notes['adjustments']), "matched the existing player 'Old Name'"));

        $this->assertSame(1, Player::count());
        $player->refresh();
        $this->assertSame('Old Name', $player->name);
        $this->assertSame('bowler', $player->primary_role);
        $this->assertSame('left_hand', $player->batting_style);

        $registration = PlayerRegistration::where('player_id', $player->id)->sole();
        $this->assertSame('Sendriya', $registration->village);
        $this->assertSame(22, $registration->age);
    }

    public function test_the_same_mobile_twice_in_a_sheet_imports_the_first_and_reports_the_second(): void
    {
        $edition = $this->edition();

        $this->import($edition, $this->sheet(
            $this->response(['Name' => 'First Try', 'Mobile 📱 Number' => '9876543210']),
            $this->response(['Name' => 'Second Try', 'Mobile 📱 Number' => '+91 9876543210']),
        ))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '1 registration created') && str_contains($message, '1 skipped'))
            ->assertSessionHas('import_notes', fn (array $notes) => str_contains(implode("\n", $notes['skipped']), 'Row 3 (Second Try): skipped — same person as row 2 (First Try)'));

        $this->assertSame(1, PlayerRegistration::count());
        $this->assertSame('First Try', Player::sole()->name);
    }

    public function test_timestamp_order_is_day_first_unless_the_data_proves_month_first(): void
    {
        $dayFirst = $this->edition();

        // 13 can only be a day, so the sheet is day/month/year; the
        // ambiguous 02/03 is then 2 March (09:00 in India = 03:30 UTC).
        $this->import($dayFirst, $this->sheet(
            $this->response(['Name' => 'Ambiguous', 'Timestamp' => '02/03/2026 09:00:00']),
            $this->response(['Name' => 'Proof', 'Timestamp' => '13/01/2026 10:00:00']),
        ))->assertSessionHasNoErrors();

        $this->assertSame(
            '2026-03-02 03:30:00',
            PlayerRegistration::where('player_id', Player::firstWhere('name', 'Ambiguous')->id)->sole()->registered_at->format('Y-m-d H:i:s'),
        );

        $monthFirst = $this->edition();

        // 13 in the second part can only be a day, so this sheet is
        // month/day/year and the same 02/03 is 3 February.
        $this->import($monthFirst, $this->sheet(
            $this->response(['Name' => 'Ambiguous US', 'Timestamp' => '02/03/2026 09:00:00']),
            $this->response(['Name' => 'Proof US', 'Timestamp' => '01/13/2026 10:00:00']),
        ))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('import_notes', fn (array $notes) => str_contains(implode("\n", $notes['info']), 'month/day/year'));

        $this->assertSame(
            '2026-02-03 03:30:00',
            PlayerRegistration::where('player_id', Player::firstWhere('name', 'Ambiguous US')->id)->sole()->registered_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_unreadable_or_mixed_timestamps_reject_the_whole_file(): void
    {
        $edition = $this->edition();

        $this->import($edition, $this->sheet(
            $this->response(['Timestamp' => '13/01/2026 10:00:00']),
            $this->response(['Timestamp' => '01/13/2026 10:00:00']),
        ))->assertSessionHasErrors('csv_file');

        $this->import($edition, $this->sheet(
            $this->response(['Timestamp' => '02/10/2026 14:35:12']),
            $this->response(['Name' => 'Bad Date', 'Timestamp' => 'sometime last week']),
        ))->assertSessionHasErrors('csv_file');

        $this->assertSame(0, Player::count());
        $this->assertSame(0, PlayerRegistration::count());
    }

    public function test_only_the_name_is_required_in_a_form_response(): void
    {
        $edition = $this->edition();

        $this->import($edition, "Timestamp,Name\n02/10/2026 14:35:12,Only Name\n")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $player = Player::sole();
        $this->assertSame('Only Name', $player->name);
        $this->assertNull($player->phone);
        $this->assertNull($player->email);
        $this->assertNull($player->primary_role);

        $registration = PlayerRegistration::sole();
        $this->assertNull($registration->age);
        $this->assertNull($registration->village);
        $this->assertNull($registration->photo_url);
        $this->assertSame('300.00', $registration->registration_fee);
        $this->assertSame('pending', $registration->payment_status);
    }

    public function test_a_windows_1252_file_is_converted_instead_of_breaking_the_import(): void
    {
        $edition = $this->edition();

        // "Jos\xE9" is "José" saved by Excel as plain CSV — not valid UTF-8.
        $this->import($edition, "Name\nJos\xE9 Kumar\n")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('José Kumar', Player::sole()->name);
    }

    public function test_the_registration_page_shows_the_imported_answers_and_safe_drive_links(): void
    {
        $registration = PlayerRegistration::factory()->create([
            'village' => 'Sendriya',
            'tehsil' => 'Multai',
            'district' => 'Betul',
            'age' => 22,
            'submitted_utr' => 'UTR-TYPED-1',
            'photo_url' => 'https://drive.google.com/open?id=PHOTO1',
            'payment_proof_url' => 'https://drive.google.com/open?id=PROOF1',
        ]);
        $registration->player->update(['date_of_birth' => null, 'batting_style' => 'left_hand']);

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.show', $registration))
            ->assertOk()
            ->assertSee('Sendriya')
            ->assertSee('Multai')
            ->assertSee('Betul')
            ->assertSee('UTR-TYPED-1')
            ->assertSee('Left hand')
            ->assertSee('22')
            ->assertSee('(as entered on the form)')
            ->assertSee('href="https://drive.google.com/open?id=PROOF1"', false)
            ->assertSee('href="https://drive.google.com/open?id=PHOTO1"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_admin_can_correct_the_imported_details_from_the_edit_page(): void
    {
        $registration = PlayerRegistration::factory()->create(['payment_status' => 'pending']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.player-registrations.edit', $registration))
            ->assertOk()
            ->assertSee('Details from the registration form')
            ->assertSee('name="village"', false)
            ->assertSee('name="payment_proof_url"', false);

        $this->actingAs($admin)->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'paid',
            'age' => 25,
            'village' => 'Sendriya',
            'tehsil' => 'Multai',
            'district' => 'Betul',
            'submitted_utr' => 'UTR-1',
            'photo_url' => 'https://drive.google.com/open?id=P',
            'payment_proof_url' => 'https://drive.google.com/open?id=S',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('player_registrations', [
            'id' => $registration->id,
            'age' => 25,
            'village' => 'Sendriya',
            'tehsil' => 'Multai',
            'district' => 'Betul',
            'submitted_utr' => 'UTR-1',
            'photo_url' => 'https://drive.google.com/open?id=P',
            'payment_proof_url' => 'https://drive.google.com/open?id=S',
        ]);

        // Clearing a field empties it.
        $this->actingAs($admin)->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'paid',
            'village' => '',
            'photo_url' => '',
        ])->assertSessionHasNoErrors();

        $registration->refresh();
        $this->assertNull($registration->village);
        $this->assertNull($registration->photo_url);
    }

    public function test_the_edit_page_rejects_an_unrealistic_age_and_links_that_are_not_google_drive_links(): void
    {
        $registration = PlayerRegistration::factory()->create(['photo_url' => null]);
        $admin = $this->admin();

        foreach (['age' => 4, 'photo_url' => 'javascript:alert(1)', 'payment_proof_url' => 'http://drive.google.com/open?id=1'] as $field => $value) {
            $this->actingAs($admin)
                ->put(route('admin.player-registrations.update', $registration), ['payment_status' => 'paid', $field => $value])
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($admin)
            ->put(route('admin.player-registrations.update', $registration), ['payment_status' => 'paid', 'photo_url' => 'https://example.com/me.png'])
            ->assertSessionHasErrors('photo_url');

        $this->assertNull($registration->fresh()->photo_url);
    }

    public function test_search_finds_registrations_by_village_district_or_typed_utr(): void
    {
        $byVillage = PlayerRegistration::factory()->create([
            'player_id' => Player::factory()->create(['name' => 'Found By Village']),
            'village' => 'Sendriya',
        ]);
        $byUtr = PlayerRegistration::factory()->create([
            'player_id' => Player::factory()->create(['name' => 'Found By Utr']),
            'submitted_utr' => 'XYZ98765',
        ]);
        PlayerRegistration::factory()->create(['player_id' => Player::factory()->create(['name' => 'Someone Else']), 'village' => 'Elsewhere']);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.player-registrations.index', ['search' => 'Sendriya']))
            ->assertOk()
            ->assertSee('Found By Village')
            ->assertDontSee('Found By Utr')
            ->assertDontSee('Someone Else');

        $this->actingAs($admin)->get(route('admin.player-registrations.index', ['search' => 'XYZ98765']))
            ->assertOk()
            ->assertSee('Found By Utr')
            ->assertDontSee('Found By Village');

        $this->assertNotNull($byVillage);
        $this->assertNotNull($byUtr);
    }
}
