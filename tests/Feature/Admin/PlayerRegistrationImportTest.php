<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PlayerRegistrationImportTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    private function csv(string $content, string $filename = 'import.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    public function test_admin_can_access_import_page_but_scorer_is_forbidden(): void
    {
        $this->actingAs($this->admin())->get(route('admin.player-registrations.import'))->assertOk();

        $this->actingAs($this->scorer())->get(route('admin.player-registrations.import'))->assertForbidden();
        $this->actingAs($this->scorer())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => Edition::factory()->create(['status' => 'active'])->id,
                'csv_file' => $this->csv("name\nSomeone\n"),
            ])
            ->assertForbidden();
    }

    public function test_valid_csv_creates_new_player_and_registration(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $content = "Name,Phone,Email,Registration Fee,Payment Status,Registered At\n"
            ."Amit Verma,9998887770,amit@example.com,1500,paid,2026-01-05\n";

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv($content),
            ])
            ->assertRedirect(route('admin.player-registrations.index', ['edition_id' => $edition->id]))
            ->assertSessionHas('success');

        $player = Player::firstWhere('email', 'amit@example.com');
        $this->assertNotNull($player);
        $this->assertSame('Amit Verma', $player->name);
        $this->assertTrue($player->is_active);

        $registration = PlayerRegistration::where('player_id', $player->id)->where('edition_id', $edition->id)->first();
        $this->assertNotNull($registration);
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('1500.00', $registration->registration_fee);
    }

    public function test_existing_player_matched_by_email_or_phone_is_reused_and_not_overwritten(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $existing = Player::factory()->create(['name' => 'Original Name', 'email' => 'match@example.com', 'phone' => '9990001111']);

        $content = "name,email\nDifferent Name In CSV,match@example.com\n";

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv($content),
            ])
            ->assertRedirect();

        $this->assertSame(1, Player::count()); // no new player created
        $existing->refresh();
        $this->assertSame('Original Name', $existing->name); // never overwritten
        $this->assertSame('9990001111', $existing->phone);

        $this->assertDatabaseHas('player_registrations', ['edition_id' => $edition->id, 'player_id' => $existing->id]);
    }

    public function test_already_registered_player_is_skipped_not_updated(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $player = Player::factory()->create(['email' => 'already@example.com']);
        $registration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'pending',
            'registration_fee' => null,
        ]);

        $content = "name,email,payment_status,registration_fee\nAlready Registered,already@example.com,paid,9999\n";

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv($content),
            ])
            ->assertSessionHas('success', function ($message) {
                return str_contains($message, '0 registrations created') && str_contains($message, '1 skipped');
            });

        $registration->refresh();
        $this->assertSame('pending', $registration->payment_status); // untouched
        $this->assertNull($registration->registration_fee);
        $this->assertSame(1, PlayerRegistration::count());
    }

    public function test_duplicate_row_inside_csv_creates_only_one_registration(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $content = "name,email\nJane Doe,jane@example.com\nJane Doe Again,jane@example.com\n";

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv($content),
            ])
            ->assertRedirect();

        $this->assertSame(1, Player::count());
        $this->assertSame(1, PlayerRegistration::count());
    }

    public function test_invalid_payment_status_anywhere_in_file_causes_zero_writes(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $content = "name,email,payment_status\nGood Row,good@example.com,paid\nBad Row,bad@example.com,not_a_status\n";

        $response = $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv($content),
            ]);

        $response->assertRedirect(route('admin.player-registrations.import'));
        $response->assertSessionHasErrors('csv_file');
        $this->assertSame(0, Player::count());
        $this->assertSame(0, PlayerRegistration::count());
    }

    public function test_email_and_phone_identity_conflict_causes_zero_writes(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        Player::factory()->create(['email' => 'byemail@example.com', 'phone' => null]);
        Player::factory()->create(['email' => null, 'phone' => '9991112222']);

        $content = "name,email,phone\nConflicted Row,byemail@example.com,9991112222\n";

        $response = $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv($content),
            ]);

        $response->assertSessionHasErrors('csv_file');
        $this->assertSame(2, Player::count()); // only the two pre-existing players
        $this->assertSame(0, PlayerRegistration::count());
    }

    public function test_inactive_matched_player_is_rejected_with_zero_writes(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        Player::factory()->create(['email' => 'inactive@example.com', 'is_active' => false]);

        $content = "name,email\nInactive Person,inactive@example.com\n";

        $response = $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv($content),
            ]);

        $response->assertSessionHasErrors('csv_file');
        $this->assertSame(0, PlayerRegistration::count());
    }

    public function test_bom_and_extra_google_forms_column_and_quoted_values_are_handled(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $content = "\xEF\xBB\xBFTimestamp,Name,Email\n"
            ."\"2026/01/01 10:00:00\",\"Doe, Jane\",jane.quoted@example.com\n";

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv($content),
            ])
            ->assertSessionHas('success');

        $player = Player::firstWhere('email', 'jane.quoted@example.com');
        $this->assertNotNull($player);
        $this->assertSame('Doe, Jane', $player->name);
    }

    public function test_header_only_and_missing_name_column_are_rejected_safely(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);

        $headerOnly = $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv("name,email\n"),
            ]);
        $headerOnly->assertSessionHasErrors('csv_file');

        $missingName = $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $this->csv("phone,email\n9998887770,x@example.com\n"),
            ]);
        $missingName->assertSessionHasErrors('csv_file');

        $this->assertSame(0, Player::count());
    }
}
