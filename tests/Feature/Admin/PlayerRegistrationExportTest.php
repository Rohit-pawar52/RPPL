<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerRegistrationExportTest extends TestCase
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

    private function streamedCsv($response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_admin_can_export_but_scorer_is_forbidden(): void
    {
        PlayerRegistration::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($this->scorer())
            ->get(route('admin.player-registrations.export'))
            ->assertForbidden();
    }

    public function test_exported_file_contains_expected_headers_and_data(): void
    {
        $edition = Edition::factory()->create(['name' => 'RPPL 2026', 'year' => 2026]);
        $player = Player::factory()->create(['name' => 'Amit Verma', 'phone' => '9998887770', 'email' => 'amit@example.com']);
        $registration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'paid',
            'registration_fee' => '1500.00',
            'registered_at' => '2026-01-05',
        ])->assignRegistrationNumber();

        $response = $this->actingAs($this->admin())->get(route('admin.player-registrations.export'));
        $response->assertOk();

        $csv = $this->streamedCsv($response);

        foreach (['Registration ID', 'Edition', 'Player Name', 'Phone', 'Email', 'Payment Status', 'Registration Fee', 'Registered At'] as $header) {
            $this->assertStringContainsString($header, $csv);
        }
        // "Registration ID" now exports the public registration_number
        // (RPPL-{year}-{id}), not the bare internal numeric id.
        $this->assertStringContainsString($registration->registration_number, $csv);
        $this->assertStringContainsString('RPPL-2026-', $csv);
        $this->assertStringContainsString('RPPL 2026', $csv);
        $this->assertStringContainsString('Amit Verma', $csv);
        $this->assertStringContainsString('9998887770', $csv);
        $this->assertStringContainsString('amit@example.com', $csv);
        $this->assertStringContainsString('Paid', $csv);
        $this->assertStringContainsString('1500.00', $csv);
        $this->assertStringContainsString('2026-01-05', $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv); // Excel-friendly UTF-8 BOM
    }

    public function test_edition_and_payment_status_filters_are_honored(): void
    {
        $editionA = Edition::factory()->create(['year' => 2025]);
        $editionB = Edition::factory()->create(['year' => 2026]);

        $matching = PlayerRegistration::factory()->create([
            'edition_id' => $editionA->id,
            'payment_status' => 'paid',
        ]);
        $wrongEdition = PlayerRegistration::factory()->create([
            'edition_id' => $editionB->id,
            'payment_status' => 'paid',
            'player_id' => Player::factory()->create(['name' => 'Wrong Edition Player'])->id,
        ]);
        $wrongStatus = PlayerRegistration::factory()->create([
            'edition_id' => $editionA->id,
            'payment_status' => 'pending',
            'player_id' => Player::factory()->create(['name' => 'Wrong Status Player'])->id,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.player-registrations.export', [
            'edition_id' => $editionA->id,
            'payment_status' => 'paid',
        ]));

        $csv = $this->streamedCsv($response);

        $this->assertStringContainsString($matching->player->name, $csv);
        $this->assertStringNotContainsString('Wrong Edition Player', $csv);
        $this->assertStringNotContainsString('Wrong Status Player', $csv);
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('rppl-registrations-2025.csv', $response->headers->get('content-disposition'));
    }

    public function test_search_filter_is_honored(): void
    {
        $matchingPlayer = Player::factory()->create(['name' => 'Distinctive Search Name']);
        PlayerRegistration::factory()->create(['player_id' => $matchingPlayer->id]);
        $otherPlayer = Player::factory()->create(['name' => 'Someone Else']);
        PlayerRegistration::factory()->create(['player_id' => $otherPlayer->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.export', ['search' => 'Distinctive Search Name']));

        $csv = $this->streamedCsv($response);

        $this->assertStringContainsString('Distinctive Search Name', $csv);
        $this->assertStringNotContainsString('Someone Else', $csv);
    }

    public function test_empty_result_still_returns_a_valid_header_only_file(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.export', ['search' => 'Nobody Matches This At All']));

        $response->assertOk();
        $csv = $this->streamedCsv($response);

        $this->assertStringContainsString('Registration ID', $csv);
        $this->assertStringContainsString('Registered At', $csv);
        // Only the header line, no data rows.
        $this->assertSame(1, count(array_filter(explode("\n", $csv))));
    }
}
