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

/**
 * Phase 3.39B — the registration_number foundation: format, the single
 * generation mechanism shared by every creation path, and the Edition
 * registration_open/registration_fee configuration fields. No guest
 * form, uploads, or admin verification workflow exist yet (later
 * phases) — this only proves the schema/model foundation is correct.
 */
class RegistrationNumberTest extends TestCase
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

    public function test_edition_stores_registration_open_and_registration_fee(): void
    {
        $edition = Edition::factory()->create(['year' => 2028]);

        $this->actingAs($this->admin())
            ->put(route('admin.editions.update', $edition), [
                'name' => $edition->name,
                'year' => $edition->year,
                'status' => $edition->status,
                'registration_open' => '1',
                'registration_fee' => '400.00',
            ])
            ->assertRedirect(route('admin.editions.index'));

        $edition->refresh();
        $this->assertTrue($edition->registration_open);
        $this->assertSame('400.00', (string) $edition->registration_fee);

        // Unchecking (omitted from the request, exactly like a real
        // unchecked HTML checkbox) must actually turn it back off, not
        // silently leave the previous value in place.
        $this->actingAs($this->admin())
            ->put(route('admin.editions.update', $edition), [
                'name' => $edition->name,
                'year' => $edition->year,
                'status' => $edition->status,
                'registration_fee' => '400.00',
            ])
            ->assertRedirect(route('admin.editions.index'));

        $this->assertFalse($edition->fresh()->registration_open);
    }

    public function test_new_edition_defaults_registration_closed(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.editions.store'), [
                'name' => 'RPPL 2029',
                'year' => 2029,
                'status' => 'upcoming',
            ])
            ->assertRedirect(route('admin.editions.index'));

        $edition = Edition::firstWhere('year', 2029);
        $this->assertNotNull($edition);
        $this->assertFalse($edition->registration_open);
        $this->assertNull($edition->registration_fee);
    }

    public function test_admin_created_registration_receives_the_correct_registration_number_format(): void
    {
        $edition = Edition::factory()->create(['year' => 2026, 'status' => 'active']);
        $player = Player::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.store'), [
                'edition_id' => $edition->id,
                'player_id' => $player->id,
                'payment_status' => 'pending',
            ])
            ->assertRedirect(route('admin.player-registrations.index'));

        $registration = PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->firstOrFail();

        $this->assertSame(sprintf('RPPL-2026-%06d', $registration->id), $registration->registration_number);
    }

    public function test_csv_imported_registration_receives_a_correctly_formatted_registration_number(): void
    {
        $edition = Edition::factory()->create(['year' => 2027, 'status' => 'active']);

        $csv = "name,phone,email\nImport Player,9998887766,import.player@example.com\n";
        $file = UploadedFile::fake()->createWithContent('registrations.csv', $csv);

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.import.store'), [
                'edition_id' => $edition->id,
                'csv_file' => $file,
            ])
            ->assertSessionHas('success');

        $registration = PlayerRegistration::where('edition_id', $edition->id)->firstOrFail();

        $this->assertSame(sprintf('RPPL-2027-%06d', $registration->id), $registration->registration_number);
    }

    public function test_registration_numbers_are_unique_across_multiple_registrations(): void
    {
        $edition = Edition::factory()->create(['year' => 2026, 'status' => 'active']);
        $admin = $this->admin();

        $numbers = [];

        foreach (range(1, 3) as $i) {
            $player = Player::factory()->create(['is_active' => true]);

            $this->actingAs($admin)->post(route('admin.player-registrations.store'), [
                'edition_id' => $edition->id,
                'player_id' => $player->id,
                'payment_status' => 'pending',
            ]);

            $numbers[] = PlayerRegistration::where('player_id', $player->id)->value('registration_number');
        }

        $this->assertCount(3, array_unique($numbers));
        foreach ($numbers as $number) {
            $this->assertMatchesRegularExpression('/^RPPL-2026-\d{6}$/', $number);
        }
    }

    public function test_registration_number_format_is_never_truncated_for_large_ids(): void
    {
        $this->assertSame('RPPL-2026-000001', PlayerRegistration::formatRegistrationNumber(2026, 1));
        $this->assertSame('RPPL-2026-123456', PlayerRegistration::formatRegistrationNumber(2026, 123456));
        // Beyond the 6-digit padding width, the id is never truncated.
        $this->assertSame('RPPL-2026-1234567', PlayerRegistration::formatRegistrationNumber(2026, 1234567));
    }
}
