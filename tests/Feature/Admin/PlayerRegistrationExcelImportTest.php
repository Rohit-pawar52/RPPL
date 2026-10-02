<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use App\Support\XlsxReader;
use App\Support\XlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/**
 * The registrations import also takes an Excel .xlsx (read without a
 * spreadsheet package), and the admin can download a sample sheet.
 */
class PlayerRegistrationExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

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

    private function upload(string $bytes, string $name = 'players.xlsx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    private function import(Edition $edition, UploadedFile $file, array $extra = [])
    {
        return $this->actingAs($this->admin())->post(route('admin.player-registrations.import.store'), [
            'edition_id' => $edition->id,
            'csv_file' => $file,
            ...$extra,
        ]);
    }

    /**
     * An .xlsx laid out the way Excel itself saves one: shared strings, a
     * phone number and age stored as numbers, and the timestamp stored as a
     * date serial in a date-formatted cell.
     */
    private function excelSavedSheet(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $serial = rtrim(sprintf('%.10f', strtotime('2026-10-03 00:20:00 UTC') / 86400 + 25569), '0');

        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Form responses 1" sheetId="1" r:id="rId7"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId7" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="22"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Timestamp</t></si><si><t>Name</t></si><si><t>Mobile Number</t></si><si><t>Age</t></si><si><t>Excel Player</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row>'
            .'<row r="3"><c r="A3" s="1"><v>'.$serial.'</v></c><c r="B3" t="s"><v>4</v></c><c r="C3"><v>9.87654321E9</v></c><c r="D3"><v>24</v></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    public function test_the_sample_sheet_downloads_as_excel_and_imports_cleanly(): void
    {
        $edition = Edition::factory()->create(['status' => 'active', 'registration_fee' => 300]);
        $admin = $this->admin();

        $download = $this->actingAs($admin)->get(route('admin.player-registrations.import.sample'))->assertOk();
        $download->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('rppl-registrations-sample.xlsx', $download->headers->get('Content-Disposition'));
        $bytes = $download->streamedContent();
        $this->assertTrue(XlsxReader::looksLikeXlsx($bytes));

        // Check only: both example players would be created, no warnings about the headers.
        $this->import($edition, $this->upload($bytes), ['dry_run' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('import_check', fn (array $check) => $check['created_registrations'] === 2
                && $check['created_players'] === 2
                && $check['notes']['info'] !== []
                && ! str_contains(implode("\n", $check['notes']['info']), 'Columns not imported'));
        $this->assertSame(0, PlayerRegistration::count());

        // The real import fills the optional answers when given and leaves them empty when not.
        $this->import($edition, $this->upload($bytes))->assertSessionHasNoErrors()->assertSessionHas('success');

        $full = Player::firstWhere('phone', '9876543210');
        $this->assertSame('Ramesh Joshi', $full->name);
        $this->assertSame('all_rounder', $full->primary_role);
        $this->assertSame('right_hand', $full->batting_style);
        $this->assertSame('right_arm', $full->bowling_style);
        $registration = PlayerRegistration::where('player_id', $full->id)->sole();
        $this->assertSame(24, $registration->age);
        $this->assertSame('Sendriya', $registration->village);
        $this->assertSame('402912345678', $registration->submitted_utr);
        $this->assertSame('2026-10-03 04:45:00', $registration->registered_at->format('Y-m-d H:i:s'));

        $minimal = Player::firstWhere('phone', '9123456780');
        $this->assertSame('Suresh Patil', $minimal->name);
        $this->assertNull($minimal->batting_style);
        $registration = PlayerRegistration::where('player_id', $minimal->id)->sole();
        $this->assertNull($registration->age);
        $this->assertNull($registration->submitted_utr);
    }

    public function test_only_an_admin_can_download_the_sample(): void
    {
        $scorer = User::factory()->create(['role_id' => Role::firstWhere('slug', 'scorer')->id]);

        $this->actingAs($scorer)->get(route('admin.player-registrations.import.sample'))->assertForbidden();
    }

    public function test_a_sheet_saved_by_excel_is_read_including_numbers_dates_and_skipped_rows(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);

        $this->import($edition, $this->upload($this->excelSavedSheet()))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '1 registration created'));

        $player = Player::sole();
        $this->assertSame('Excel Player', $player->name);
        $this->assertSame('9876543210', $player->phone, 'a long number stored in scientific notation comes back as its digits');

        $registration = PlayerRegistration::sole();
        $this->assertSame(24, $registration->age);
        // 12:20 AM on 3 October read in India time (the date cell has no zone), stored as UTC.
        $this->assertSame('2026-10-02 18:50:00', $registration->registered_at->format('Y-m-d H:i:s'));
    }

    public function test_an_unreadable_excel_file_or_another_file_type_is_refused_without_writing_anything(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);

        $this->import($edition, $this->upload("PK\x03\x04 this is not really a workbook"))->assertSessionHasErrors('csv_file');
        $this->import($edition, $this->upload('%PDF-1.4', 'players.pdf'))->assertSessionHasErrors('csv_file');

        $this->assertSame(0, Player::count());
        $this->assertSame(0, PlayerRegistration::count());
    }

    public function test_the_writer_and_reader_round_trip_text_exactly(): void
    {
        $rows = [['Name', 'Mobile Number'], ['Zoë <&> "Q"', '0987654321'], ['', 'x']];
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, XlsxWriter::make($rows));

        $this->assertSame([['Name', 'Mobile Number'], ['Zoë <&> "Q"', '0987654321'], ['', 'x']], XlsxReader::records($path));

        @unlink($path);
    }
}
